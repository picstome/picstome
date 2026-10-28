<?php

namespace App\Traits;

trait InteractsWithTallStackModals
{
    /**
     * Dispatch the browser event TallStackUI listens to for a named modal.
     */
    public function openModal(string $name): void
    {
        $this->dispatch($this->modalEvent($name, 'open'));
    }

    /**
     * Dispatch the browser event TallStackUI listens to for a named modal.
     */
    public function closeModal(string $name): void
    {
        $this->dispatch($this->modalEvent($name, 'close'));
    }

    protected function modalEvent(string $name, string $action): string
    {
        return 'modal:'.str($name)->slug()->kebab().'-'.$action;
    }
}
