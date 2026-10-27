<?php
namespace Lms\Exceptions;

class AuthenticationException extends \RuntimeException
{
    protected $code = 401;
}
