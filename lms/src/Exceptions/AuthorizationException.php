<?php
namespace Lms\Exceptions;

class AuthorizationException extends \RuntimeException
{
    protected $code = 403;
}
