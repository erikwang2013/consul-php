<?php

namespace Erikwang\Consul\Client;

class Promise
{
    private $executor;
    private array $thenCallbacks = [];
    private array $catchCallbacks = [];
    private bool $resolved = false;
    private $value;
    private $exception;

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

    public function wait()
    {
        if (!$this->resolved) {
            try {
                $this->value = ($this->executor)();
                $this->exception = null;
            } catch (\Throwable $e) {
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
