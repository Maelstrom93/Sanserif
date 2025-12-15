<?php
declare(strict_types=1);
class ApiException extends RuntimeException {}
class DbException extends RuntimeException {}
class BadRequestException extends RuntimeException {}
class ValidationException extends RuntimeException {}
class NotFoundException extends RuntimeException {}
class ConflictException extends RuntimeException {}
class OperationFailedException extends RuntimeException {}

