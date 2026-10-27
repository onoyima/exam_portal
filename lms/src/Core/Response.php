<?php
namespace Lms\Core;

class Response
{
    private mixed $data = null;
    private int $statusCode = 200;
    private array $headers = [];
    private bool $sent = false;

    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function json(mixed $data, int $status = null): void
    {
        if ($status) {
            $this->statusCode = $status;
        }

        $this->data = $data;
        $this->send('application/json');
    }

    public function success(mixed $data = null, array $meta = [], int $status = 200): void
    {
        $response = ['success' => true];
        if ($data !== null) {
            $response['data'] = $data;
        }
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        $this->json($response, $status);
    }

    public function created(mixed $data = null): void
    {
        $this->success($data, [], 201);
    }

    public function noContent(): void
    {
        $this->json(['success' => true], 204);
    }

    public function error(string $code, string $message, int $status = 400, array $details = []): void
    {
        $error = [
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ];
        if (!empty($details)) {
            $error['error']['details'] = $details;
        }
        $this->json($error, $status);
    }

    public function validationError(array $errors): void
    {
        $this->error('VALIDATION_ERROR', 'The given data was invalid.', 422, $errors);
    }

    public function notFound(string $message = 'Resource not found'): void
    {
        $this->error('NOT_FOUND', $message, 404);
    }

    public function unauthorized(string $message = 'Unauthorized'): void
    {
        $this->error('AUTH_ERROR', $message, 401);
    }

    public function forbidden(string $message = 'Forbidden'): void
    {
        $this->error('FORBIDDEN', $message, 403);
    }

    public function serverError(string $message = 'Internal server error'): void
    {
        $this->error('INTERNAL_ERROR', $message, 500);
    }

    public function paginated(array $items, int $total, int $page, int $perPage): void
    {
        $meta = [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => (int) ceil($total / max($perPage, 1)),
        ];
        $this->success($items, $meta);
    }

    public function redirect(string $url): void
    {
        $this->sent = true;
        header("Location: {$url}");
        exit;
    }

    public function download(string $filePath, string $filename = null): void
    {
        if (!file_exists($filePath)) {
            $this->notFound('File not found');
            return;
        }

        $filename = $filename ?? basename($filePath);
        $this->sent = true;

        header('Content-Type: ' . mime_content_type($filePath));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache');
        readfile($filePath);
        exit;
    }

    public function send(string $contentType = 'application/json'): void
    {
        if ($this->sent) {
            return;
        }

        $this->sent = true;
        http_response_code($this->statusCode);
        header("Content-Type: {$contentType}");

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->data !== null) {
            echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        exit;
    }

    public function isSent(): bool
    {
        return $this->sent;
    }
}
