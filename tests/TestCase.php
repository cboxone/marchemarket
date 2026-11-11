<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Vérifie qu'un e-mail a bien été émis, qu'il soit envoyé tout de suite
     * ou mis en file d'attente (les Mailables ShouldQueue de l'application
     * sont enregistrés comme « queued » par Mail::fake()).
     */
    protected function assertMailed(string $mailable, ?callable $callback = null): void
    {
        try {
            \Illuminate\Support\Facades\Mail::assertQueued($mailable, $callback);
        } catch (\PHPUnit\Framework\AssertionFailedError) {
            \Illuminate\Support\Facades\Mail::assertSent($mailable, $callback);
        }
    }
}
