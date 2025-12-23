<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Stripe;

use App\Http\Controllers\Controller;
use App\Traits\StripeResponseTrait;
use App\Traits\DiscountCalculationTrait;
use App\Traits\PriceFormatterTrait;
use App\Exceptions\StripeException;
use Illuminate\Http\JsonResponse;
use Stripe\Exception\ApiErrorException;
use Psr\Log\LoggerInterface;

/**
 * Base controller for all Stripe-related controllers
 * Provides common functionality, error handling, and response formatting
 */
abstract class BaseStripeController extends Controller
{
    use StripeResponseTrait;
    use DiscountCalculationTrait;
    use PriceFormatterTrait;

    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Execute a Stripe operation with error handling
     *
     * @param callable $operation
     * @param string $operationName For logging purposes
     * @return JsonResponse
     */
    protected function executeStripeOperation(callable $operation, string $operationName = 'Stripe operation'): JsonResponse
    {
        try {
            return $operation();
        } catch (ApiErrorException $e) {
            return $this->handleStripeException($e, $operationName);
        } catch (StripeException $e) {
            return $this->errorResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->handleGeneralException($e, $operationName);
        }
    }

    /**
     * Handle Stripe API exceptions
     *
     * @param ApiErrorException $e
     * @param string $operationName
     * @return JsonResponse
     */
    protected function handleStripeException(ApiErrorException $e, string $operationName): JsonResponse
    {
        $stripeException = StripeException::fromStripeException($e);

        $this->logger->error("Stripe error during {$operationName}", [
            'error_code' => $stripeException->getStripeErrorCode(),
            'error_type' => $stripeException->getStripeErrorType(),
            'message' => $e->getMessage(),
        ]);

        return response()->json($stripeException->toArray(), 400);
    }

    /**
     * Handle general exceptions
     *
     * @param \Exception $e
     * @param string $operationName
     * @return JsonResponse
     */
    protected function handleGeneralException(\Exception $e, string $operationName): JsonResponse
    {
        $this->logger->error("Error during {$operationName}", [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Don't expose internal error details to the client
        $message = app()->environment('production')
            ? 'An unexpected error occurred. Please try again later.'
            : $e->getMessage();

        return $this->errorResponse($message);
    }

    /**
     * Get authenticated user or return error response
     *
     * @return mixed User object or JsonResponse
     */
    protected function getAuthenticatedUser(): mixed
    {
        $user = auth()->user();

        if (!$user) {
            return $this->unauthorizedResponse('User not authenticated');
        }

        return $user;
    }

    /**
     * Log a successful operation
     *
     * @param string $operation
     * @param array $context
     * @return void
     */
    protected function logSuccess(string $operation, array $context = []): void
    {
        $this->logger->info("Stripe operation successful: {$operation}", $context);
    }

    /**
     * Log a warning
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }
}
