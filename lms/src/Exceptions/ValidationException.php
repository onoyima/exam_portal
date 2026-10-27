<?php
namespace Lms\Exceptions;

class ValidationException extends \RuntimeException
{
    protected $code = 422;
    private array $errors;

    public function __construct(string $message = 'Validation failed', array $errors = [])
    {
        parent::__construct($message, 422);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
