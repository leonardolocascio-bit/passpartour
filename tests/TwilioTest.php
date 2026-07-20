<?php

namespace App\Tests;

use App\Service\TwilioMessenger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TwilioTest extends KernelTestCase
{
    private TwilioMessenger $twilio;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->twilio = static::getContainer()->get(TwilioMessenger::class);
    }

    public function testNonConfiguratoSenzaCredenziali(): void
    {
        self::assertFalse($this->twilio->configurato());
        self::assertFalse($this->twilio->puoInviare('sms'));
        self::assertFalse($this->twilio->puoInviare('whatsapp'));

        // invio protetto: ritorna errore senza chiamare Twilio
        $r = $this->twilio->invia('sms', '3401234567', 'ciao');
        self::assertFalse($r['ok']);
    }

    public function testNormalizzazioneNumeroE164(): void
    {
        self::assertSame('+393401234567', $this->twilio->normalizzaNumero('340 123 4567'));
        self::assertSame('+393401234567', $this->twilio->normalizzaNumero('+39 340 1234567'));
        self::assertSame('+441234567', $this->twilio->normalizzaNumero('0044 1234567'));
        self::assertSame('', $this->twilio->normalizzaNumero('---'));
    }
}
