<?php

namespace App\Domain\Dto;

use Symfony\Component\HttpFoundation\Response;

class ApiResponseDto
{
    public function __construct(
        private bool $isError = false,
        private int $code = Response::HTTP_OK,
        private array $errors = [],
        private array $data = []
    ) {
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function setIsError(bool $isError): self
    {
        $this->isError = $isError;
        return $this;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function setCode(int $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'is_error' => $this->isError,
            'code' => $this->code,
            'errors' => $this->errors,
            'data' => $this->data
        ];
    }
}
