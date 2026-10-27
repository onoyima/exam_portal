<?php
namespace Lms\Models;

use Lms\Core\Model;

class ExamSyncLog extends Model
{
    protected static string $table = 'exam_sync_log';
    protected static bool $useTimestamps = false;

    public function isSuccess(): bool
    {
        return $this->attributes['status'] === 'success';
    }

    public function isFailed(): bool
    {
        return $this->attributes['status'] === 'failed';
    }

    public function getRequestPayload(): ?array
    {
        $data = $this->attributes['request_payload'] ?? null;
        return is_string($data) ? json_decode($data, true) : $data;
    }

    public function getResponsePayload(): ?array
    {
        $data = $this->attributes['response_payload'] ?? null;
        return is_string($data) ? json_decode($data, true) : $data;
    }

    public static function record(
        string $direction,
        string $entityType,
        ?int $entityId,
        string $action,
        ?array $requestPayload = null,
        ?array $responsePayload = null,
        ?int $statusCode = null,
        string $status = 'pending',
        ?string $errorMessage = null
    ): static {
        $log = new static();
        $log->direction = $direction;
        $log->entity_type = $entityType;
        $log->entity_id = $entityId;
        $log->action = $action;
        $log->request_payload = $requestPayload ? json_encode($requestPayload) : null;
        $log->response_payload = $responsePayload ? json_encode($responsePayload) : null;
        $log->status_code = $statusCode;
        $log->status = $status;
        $log->error_message = $errorMessage;
        $log->save();
        return $log;
    }
}
