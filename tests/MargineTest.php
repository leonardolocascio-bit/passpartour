<?php

namespace App\Tests;

use App\Service\CalcolatoreMargine;
use PHPUnit\Framework\TestCase;

class MargineTest extends TestCase
{
    private CalcolatoreMargine $calc;

    protected function setUp(): void
    {
        $this->calc = new CalcolatoreMargine();
    }

    public function testDatiInsufficientiRestituisceNull(): void
    {
        self::assertNull($this->calc->calcolaDa([]));
        self::assertNull($this->calc->calcolaDa(['quotaVendita' => '1000'])); // manca tipo
        self::assertNull($this->calc->calcolaDa(['quotaVendita' => '1000', 'tipo' => 'netta'])); // manca quota netta
    }

    public function testQuotaNettaAlNettoIva(): void
    {
        $r = $this->calc->calcolaDa([
            'quotaVendita' => '1990', 'tipo' => 'netta', 'quotaNetta' => '1650', 'ivaPercentuale' => '22',
        ]);
        self::assertSame(340.0, $r['commissioneLorda']);
        self::assertSame(340.0, $r['imponibile']); // MOL: al netto IVA l'IVA si aggiunge sopra
        self::assertSame(74.80, $r['iva']);
        self::assertSame(0.0, $r['ritenuta']);
    }

    public function testQuotaNettaLordoIvaScorporo(): void
    {
        $r = $this->calc->calcolaDa([
            'quotaVendita' => '1990', 'tipo' => 'netta', 'quotaNetta' => '1650',
            'ivaPercentuale' => '22', 'nettaLordoIva' => true,
        ]);
        self::assertSame(340.0, $r['commissioneLorda']);
        self::assertSame(278.69, $r['imponibile']); // 340 / 1.22
        self::assertSame(61.31, $r['iva']);
    }

    public function testCommissionabilePercentuale(): void
    {
        $r = $this->calc->calcolaDa([
            'quotaVendita' => '1000', 'tipo' => 'commissionabile',
            'commissioneModo' => 'percentuale', 'commissioneValore' => '12',
        ]);
        self::assertSame(120.0, $r['commissioneLorda']);
        self::assertSame(120.0, $r['imponibile']);
        self::assertSame(26.40, $r['iva']); // default 22%
    }

    public function testCommissionabileFissoLordoIvaConRitenuta(): void
    {
        $r = $this->calc->calcolaDa([
            'quotaVendita' => '2000', 'tipo' => 'commissionabile',
            'commissioneModo' => 'fisso', 'commissioneValore' => '244',
            'ivaPercentuale' => '22', 'nettaLordoIva' => true, 'ritenutaPercentuale' => '20',
        ]);
        self::assertSame(244.0, $r['commissioneLorda']);
        self::assertSame(200.0, $r['imponibile']); // 244 / 1.22
        self::assertSame(44.0, $r['iva']);
        self::assertSame(40.0, $r['ritenuta']); // 20% di 200
        self::assertSame(160.0, $r['nettoDopoRitenuta']);
    }

    public function testVirgolaDecimaleAccettata(): void
    {
        $r = $this->calc->calcolaDa([
            'quotaVendita' => '1000,50', 'tipo' => 'commissionabile',
            'commissioneModo' => 'percentuale', 'commissioneValore' => '10',
        ]);
        self::assertSame(100.05, $r['commissioneLorda']);
    }
}
