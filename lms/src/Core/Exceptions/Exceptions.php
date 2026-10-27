<?php
namespace Lms\Core\Exceptions;

class AuthenticationException extends \RuntimeException
{
    protected $code = 401;
}

class AuthorizationException extends \RuntimeException
{
    protected $code = 403;
}

class NotFoundException extends \RuntimeException
{
    protected $code = 404;
}

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

class ConflictException extends \RuntimeException
{
    protected $code = 409;
}

class IntegrationException extends \RuntimeException
{
    protected $code = 502;
}
