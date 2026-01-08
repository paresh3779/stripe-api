<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stripe;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\StripeCustomer;
use App\Mail\InvoicePaidMail;
use App\Mail\InvoiceCreatedMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

/**
 * Centralized Invoice Management Controller
 * Handles: Listing, Viewing, Downloading, Printing, Stripe Integration
 */
class InvoiceController extends Controller
{
    public function __construct()
    {
        \Stripe\Stripe::setApiKey(config('stripe.secret'));
    }

    /**
     * Get all invoices for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $query = Invoice::where('user_id', $user->id)
                ->with(['subscription.product'])
                ->orderBy('created_at', 'desc');

            // Optional filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('subscription_id')) {
                $query->where('subscription_id', $request->subscription_id);
            }

            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }

            $invoices = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $invoices->items(),
                'meta' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('InvoiceController@index error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoices',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get a single invoice by ID
     */
    public function show(Request $request, string $invoiceId): JsonResponse
    {
        try {
            $user = $request->user();
            $invoice = Invoice::where('id', $invoiceId)
                ->where('user_id', $user->id)
                ->with(['subscription.product', 'user'])
                ->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatInvoice($invoice),
            ]);
        } catch (\Exception $e) {
            Log::error('InvoiceController@show error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoice',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get invoice PDF download URL
     */
    public function downloadPdf(Request $request, string $invoiceId): JsonResponse
    {
        try {
            $user = $request->user();
            $invoice = Invoice::where('id', $invoiceId)
                ->where('user_id', $user->id)
                ->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // If we have cached PDF URL, return it
            if ($invoice->invoice_pdf) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'pdf_url' => $invoice->invoice_pdf,
                        'filename' => 'invoice-' . ($invoice->number ?? $invoice->id) . '.pdf',
                    ],
                ]);
            }

            // Fetch from Stripe if not cached
            if ($invoice->stripe_invoice_id) {
                $stripeInvoice = \Stripe\Invoice::retrieve($invoice->stripe_invoice_id);
                
                if ($stripeInvoice->invoice_pdf) {
                    // Update cached URL
                    $invoice->update(['invoice_pdf' => $stripeInvoice->invoice_pdf]);
                    
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'pdf_url' => $stripeInvoice->invoice_pdf,
                            'filename' => 'invoice-' . ($invoice->number ?? $invoice->id) . '.pdf',
                        ],
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Invoice PDF not available',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('InvoiceController@downloadPdf error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get invoice PDF',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get Stripe hosted invoice URL (for viewing in Stripe)
     */
    public function viewOnStripe(Request $request, string $invoiceId): JsonResponse
    {
        try {
            $user = $request->user();
            $invoice = Invoice::where('id', $invoiceId)
                ->where('user_id', $user->id)
                ->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // If we have cached hosted URL, return it
            if ($invoice->hosted_invoice_url) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'hosted_url' => $invoice->hosted_invoice_url,
                    ],
                ]);
            }

            // Fetch from Stripe if not cached
            if ($invoice->stripe_invoice_id) {
                $stripeInvoice = \Stripe\Invoice::retrieve($invoice->stripe_invoice_id);
                
                if ($stripeInvoice->hosted_invoice_url) {
                    // Update cached URL
                    $invoice->update(['hosted_invoice_url' => $stripeInvoice->hosted_invoice_url]);
                    
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'hosted_url' => $stripeInvoice->hosted_invoice_url,
                        ],
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Hosted invoice URL not available',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('InvoiceController@viewOnStripe error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get hosted invoice URL',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get invoice data formatted for printing
     */
    public function printData(Request $request, string $invoiceId): JsonResponse
    {
        try {
            $user = $request->user();
            $invoice = Invoice::where('id', $invoiceId)
                ->where('user_id', $user->id)
                ->with(['subscription.product', 'user'])
                ->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Format for print-friendly display
            $printData = [
                'invoice_number' => $invoice->number ?? 'INV-' . strtoupper(substr($invoice->id, 0, 8)),
                'invoice_date' => $invoice->created_at->format('F j, Y'),
                'due_date' => $invoice->due_date?->format('F j, Y') ?? 'Upon Receipt',
                'paid_date' => $invoice->paid_at?->format('F j, Y'),
                'status' => ucfirst($invoice->status),
                'customer' => [
                    'name' => $invoice->user->name ?? 'Customer',
                    'email' => $invoice->user->email,
                ],
                'billing_period' => [
                    'start' => $invoice->period_start?->format('M j, Y'),
                    'end' => $invoice->period_end?->format('M j, Y'),
                ],
                'line_items' => $invoice->line_items ?? [],
                'subtotal' => $this->formatAmount($invoice->subtotal, $invoice->currency),
                'tax' => $this->formatAmount($invoice->tax ?? 0, $invoice->currency),
                'total' => $this->formatAmount($invoice->total, $invoice->currency),
                'amount_paid' => $this->formatAmount($invoice->amount_paid, $invoice->currency),
                'amount_due' => $this->formatAmount($invoice->amount_due, $invoice->currency),
                'currency' => strtoupper($invoice->currency),
                'subscription' => $invoice->subscription ? [
                    'product_name' => $invoice->subscription->product?->name ?? 'Subscription',
                    'description' => $invoice->subscription->product?->description,
                ] : null,
                'company' => [
                    'name' => config('app.name'),
                    'address' => config('app.company_address', ''),
                    'email' => config('mail.from.address'),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $printData,
            ]);
        } catch (\Exception $e) {
            Log::error('InvoiceController@printData error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get print data',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Resend invoice email to user
     */
    public function resendEmail(Request $request, string $invoiceId): JsonResponse
    {
        try {
            $user = $request->user();
            $invoice = Invoice::where('id', $invoiceId)
                ->where('user_id', $user->id)
                ->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Send appropriate email based on status
            if ($invoice->isPaid()) {
                Mail::to($user->email)->queue(new InvoicePaidMail($invoice));
            } else {
                Mail::to($user->email)->queue(new InvoiceCreatedMail($invoice));
            }

            Log::info('Invoice email resent', ['invoice_id' => $invoice->id, 'user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice email sent successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('InvoiceController@resendEmail error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invoice email',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get invoice statistics for the user
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $stats = [
                'total_invoices' => Invoice::where('user_id', $user->id)->count(),
                'paid_invoices' => Invoice::where('user_id', $user->id)->where('status', 'paid')->count(),
                'open_invoices' => Invoice::where('user_id', $user->id)->where('status', 'open')->count(),
                'total_paid_amount' => Invoice::where('user_id', $user->id)
                    ->where('status', 'paid')
                    ->sum('amount_paid'),
                'pending_amount' => Invoice::where('user_id', $user->id)
                    ->whereIn('status', ['open', 'draft'])
                    ->sum('amount_due'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('InvoiceController@statistics error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get invoice statistics',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Sync invoices from Stripe for the authenticated user
     */
    public function syncFromStripe(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $stripeCustomer = StripeCustomer::where('user_id', $user->id)->first();

            if (!$stripeCustomer) {
                return response()->json([
                    'success' => true,
                    'message' => 'No Stripe customer found',
                    'data' => ['synced_count' => 0],
                ]);
            }

            $invoices = \Stripe\Invoice::all([
                'customer' => $stripeCustomer->stripe_customer_id,
                'limit' => 100,
            ]);

            $syncedCount = 0;
            foreach ($invoices->data as $stripeInvoice) {
                $this->syncInvoice($stripeInvoice, $user->id);
                $syncedCount++;
            }

            Log::info('Invoices synced from Stripe', ['user_id' => $user->id, 'count' => $syncedCount]);

            return response()->json([
                'success' => true,
                'message' => "Synced {$syncedCount} invoices from Stripe",
                'data' => ['synced_count' => $syncedCount],
            ]);
        } catch (\Exception $e) {
            Log::error('InvoiceController@syncFromStripe error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync invoices from Stripe',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Format invoice for API response
     */
    private function formatInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'stripe_invoice_id' => $invoice->stripe_invoice_id,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'amount_due' => $invoice->amount_due,
            'amount_paid' => $invoice->amount_paid,
            'amount_remaining' => $invoice->amount_remaining,
            'subtotal' => $invoice->subtotal,
            'total' => $invoice->total,
            'tax' => $invoice->tax,
            'currency' => $invoice->currency,
            'description' => $invoice->description,
            'hosted_invoice_url' => $invoice->hosted_invoice_url,
            'invoice_pdf' => $invoice->invoice_pdf,
            'due_date' => $invoice->due_date?->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'period_start' => $invoice->period_start?->toIso8601String(),
            'period_end' => $invoice->period_end?->toIso8601String(),
            'line_items' => $invoice->line_items,
            'created_at' => $invoice->created_at->toIso8601String(),
            'formatted' => [
                'total' => $this->formatAmount($invoice->total, $invoice->currency),
                'amount_paid' => $this->formatAmount($invoice->amount_paid, $invoice->currency),
                'amount_due' => $this->formatAmount($invoice->amount_due, $invoice->currency),
            ],
            'subscription' => $invoice->subscription ? [
                'id' => $invoice->subscription->id,
                'product_name' => $invoice->subscription->product?->name ?? 'Subscription',
                'status' => $invoice->subscription->status,
            ] : null,
            'user' => [
                'name' => $invoice->user->name ?? 'Customer',
                'email' => $invoice->user->email,
            ],
        ];
    }

    /**
     * Format amount to currency string
     */
    private function formatAmount(int $amount, string $currency): string
    {
        return number_format($amount / 100, 2) . ' ' . strtoupper($currency);
    }

    /**
     * Sync a single invoice from Stripe
     */
    private function syncInvoice(object $stripeInvoice, string $userId): Invoice
    {
        $subscription = null;
        if ($stripeInvoice->subscription) {
            $subscription = \App\Models\Subscription::where('stripe_subscription_id', $stripeInvoice->subscription)->first();
        }

        $lineItems = [];
        if ($stripeInvoice->lines && $stripeInvoice->lines->data) {
            foreach ($stripeInvoice->lines->data as $line) {
                $lineItems[] = [
                    'description' => $line->description,
                    'amount' => $line->amount,
                    'quantity' => $line->quantity ?? 1,
                ];
            }
        }

        return Invoice::updateOrCreate(
            ['stripe_invoice_id' => $stripeInvoice->id],
            [
                'user_id' => $userId,
                'subscription_id' => $subscription?->id,
                'stripe_customer_id' => $stripeInvoice->customer,
                'number' => $stripeInvoice->number,
                'status' => $stripeInvoice->status,
                'amount_due' => $stripeInvoice->amount_due,
                'amount_paid' => $stripeInvoice->amount_paid,
                'amount_remaining' => $stripeInvoice->amount_remaining,
                'subtotal' => $stripeInvoice->subtotal,
                'total' => $stripeInvoice->total,
                'tax' => $stripeInvoice->tax ?? 0,
                'currency' => $stripeInvoice->currency,
                'description' => $stripeInvoice->description,
                'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url,
                'invoice_pdf' => $stripeInvoice->invoice_pdf,
                'due_date' => $stripeInvoice->due_date 
                    ? \Carbon\Carbon::createFromTimestamp($stripeInvoice->due_date) 
                    : null,
                'paid_at' => $stripeInvoice->status === 'paid' && $stripeInvoice->status_transitions?->paid_at
                    ? \Carbon\Carbon::createFromTimestamp($stripeInvoice->status_transitions->paid_at)
                    : null,
                'period_start' => $stripeInvoice->period_start 
                    ? \Carbon\Carbon::createFromTimestamp($stripeInvoice->period_start) 
                    : null,
                'period_end' => $stripeInvoice->period_end 
                    ? \Carbon\Carbon::createFromTimestamp($stripeInvoice->period_end) 
                    : null,
                'line_items' => $lineItems,
                'metadata' => (array)($stripeInvoice->metadata ?? []),
            ]
        );
    }
}
