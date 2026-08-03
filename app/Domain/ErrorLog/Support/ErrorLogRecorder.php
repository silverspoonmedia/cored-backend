<?php

namespace App\Domain\ErrorLog\Support;

use App\Domain\ErrorLog\Models\ErrorLog;
use App\Domain\ErrorLog\Repositories\Contracts\ErrorLogRepositoryInterface;
use App\Exceptions\BusinessRuleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Persists grouped application errors. Never throws to callers.
 *
 * Octane-safe: no static request state; recursion guard is instance-local
 * and reset on each call path (singleton may be long-lived — use flag only
 * during active write, always cleared in finally).
 */
class ErrorLogRecorder
{
    private bool $recording = false;

    public function __construct(
        private readonly ErrorLogRepositoryInterface $repository,
        private readonly ErrorFingerprint $fingerprint,
        private readonly ErrorContextRedactor $redactor,
    ) {}

    public function recordException(Throwable $e): void
    {
        if (! $this->shouldStoreException($e)) {
            return;
        }

        $file = $e->getFile();
        $line = $e->getLine();

        $this->persist([
            'type' => ErrorLog::TYPE_EXCEPTION,
            'level' => 'error',
            'exception_class' => $e::class,
            'message' => $e->getMessage() !== '' ? $e->getMessage() : $e::class,
            'file' => $file !== '' ? $file : null,
            'line' => $line > 0 ? $line : null,
            'trace' => $this->truncate($e->getTraceAsString(), (int) setting_config('errorlog.max_trace_length', 65536)),
            'context' => $this->requestContext(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordLog(string $level, string $message, array $context = [], ?string $channel = null): void
    {
        if (! setting_config('errorlog.enabled', true) || $this->recording) {
            return;
        }

        $level = strtolower($level);

        if (! in_array($level, ['error', 'critical', 'alert', 'emergency'], true)) {
            return;
        }

        // Exceptions are stored only via recordException() (Laravel reportable).
        // Default exception reporting also writes Log::error with the Throwable —
        // skip those so the same failure is not double-counted as type=log.
        if (($context['exception'] ?? null) instanceof Throwable) {
            return;
        }

        $mergedContext = array_merge(
            $this->requestContext(),
            $this->redactor->redact($context),
        );

        if ($channel !== null && $channel !== '') {
            $mergedContext['channel'] = $channel;
        }

        $this->persist([
            'type' => ErrorLog::TYPE_LOG,
            'level' => $level,
            'exception_class' => null,
            'message' => $message !== '' ? $message : 'log',
            'file' => null,
            'line' => null,
            'trace' => null,
            'context' => $mergedContext,
        ]);
    }

    private function shouldStoreException(Throwable $e): bool
    {
        if (! setting_config('errorlog.enabled', true) || $this->recording) {
            return false;
        }

        if ($e instanceof BusinessRuleException) {
            return false;
        }

        foreach (setting_config('errorlog.dont_store', []) as $class) {
            if (is_string($class) && $class !== '' && $e instanceof $class) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{
     *     type: string,
     *     level: string,
     *     exception_class: ?string,
     *     message: string,
     *     file: ?string,
     *     line: ?int,
     *     trace: ?string,
     *     context: array<string, mixed>
     * }  $payload
     */
    private function persist(array $payload): void
    {
        if ($this->recording) {
            return;
        }

        $this->recording = true;

        try {
            $maxMessage = (int) setting_config('errorlog.max_message_length', 2000);
            $message = $this->truncate($payload['message'], $maxMessage);

            $fingerprint = $this->fingerprint->make(
                $payload['type'],
                $payload['level'],
                $payload['exception_class'],
                $message,
                $payload['file'],
                $payload['line'],
            );

            $context = $this->limitContext($payload['context']);

            $this->repository->upsertOccurrence(
                $fingerprint,
                [
                    'type' => $payload['type'],
                    'level' => $payload['level'],
                    'exception_class' => $payload['exception_class'],
                    'message' => $message,
                    'file' => $payload['file'] !== null
                        ? $this->truncate($payload['file'], 500)
                        : null,
                    'line' => $payload['line'],
                    'trace' => $payload['trace'],
                    'context' => $context,
                ],
            );
        } catch (Throwable) {
            // Never break the request or logging pipeline.
        } finally {
            $this->recording = false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requestContext(): array
    {
        try {
            if (! $this->appHasRequest()) {
                return [];
            }

            /** @var Request $request */
            $request = request();

            $userId = null;
            try {
                $userId = Auth::guard('api')->id() ?? Auth::id();
            } catch (Throwable) {
                $userId = null;
            }

            return $this->redactor->redact([
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'ip' => $request->ip(),
                'user_id' => $userId,
                'user_agent' => $request->userAgent(),
            ]);
        } catch (Throwable) {
            return [];
        }
    }

    private function appHasRequest(): bool
    {
        try {
            return app()->bound('request') && request() instanceof Request;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function limitContext(array $context): array
    {
        $max = (int) setting_config('errorlog.max_context_json_length', 32768);
        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false || strlen($json) <= $max) {
            return $context;
        }

        return [
            '_truncated' => true,
            'preview' => $this->truncate($json, $max),
        ];
    }

    private function truncate(string $value, int $max): string
    {
        if ($max <= 0 || strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max).'…';
    }
}
