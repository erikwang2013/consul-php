<?php

namespace Erikwang2013\Consul\Client;

use Throwable;

class Promise
{
    /** @var callable */
    private $executor;
    private array $thenCallbacks = [];
    private array $catchCallbacks = [];
    private bool $resolved = false;
    private mixed $value = null;
    private ?Throwable $exception = null;

    public function __construct(callable $executor)
    {
        $this->executor = $executor;
    }

    public function then(callable $onFulfilled): self
    {
        if ($this->resolved && $this->exception === null) {
            $onFulfilled($this->value);
            return $this;
        }

        $this->thenCallbacks[] = $onFulfilled;
        return $this;
    }

    public function catch(callable $onRejected): self
    {
        if ($this->resolved && $this->exception !== null) {
            $onRejected($this->exception);
            return $this;
        }

        $this->catchCallbacks[] = $onRejected;
        return $this;
    }

    public function wait(): mixed
    {
        if (!$this->resolved) {
            try {
                $this->value = ($this->executor)();
                $this->exception = null;
            } catch (Throwable $e) {
                $this->exception = $e;
            }
            $this->resolved = true;

            if ($this->exception !== null) {
                foreach ($this->catchCallbacks as $cb) {
                    $cb($this->exception);
                }
                throw $this->exception;
            }

            foreach ($this->thenCallbacks as $cb) {
                $cb($this->value);
            }
        }

        return $this->value;
    }
}
