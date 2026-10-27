<?php
namespace Lms\Exceptions;

class ConflictException extends \RuntimeException
{
    protected $code = 409;
}
