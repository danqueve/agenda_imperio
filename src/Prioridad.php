<?php
declare(strict_types=1);

/**
 * Semáforo de prioridad. Se evalúa en orden ROJO -> NARANJA -> AMARILLO
 * -> VERDE, primera coincidencia gana (ver plan §6).
 */
final class Prioridad
{
    /** @param string|null $fechaUltimaGestion DATETIME de la última gestión del ticket, o null si nunca se gestionó */
    public static function calcular(
        int $diasAtraso,
        ?string $fechaUltimaGestion,
        bool $agendaIncumplida,
        bool $agendaFutura
    ): string {
        $conGestionReciente = self::tieneGestionReciente($fechaUltimaGestion, 5);

        if ($diasAtraso >= 15 && !$conGestionReciente) {
            return 'rojo';
        }

        if ($agendaIncumplida || ($diasAtraso >= 10 && $diasAtraso <= 14)) {
            return 'naranja';
        }

        if (($diasAtraso >= 8 && $diasAtraso <= 9) || $conGestionReciente) {
            return 'amarillo';
        }

        // Agenda futura vigente, o el caso borde de días de atraso < 8 con
        // el ticket todavía abierto (pago parcial que bajó el atraso sin
        // que se haya cerrado el ticket - ver plan §6).
        return 'verde';
    }

    /** Etiqueta + emoji para mostrar en la UI. */
    public static function etiqueta(string $prioridad): string
    {
        return match ($prioridad) {
            'rojo'     => '🔴 Rojo',
            'naranja'  => '🟠 Naranja',
            'amarillo' => '🟡 Amarillo',
            'verde'    => '🟢 Verde',
            default    => $prioridad,
        };
    }

    private static function tieneGestionReciente(?string $fechaUltimaGestion, int $dias): bool
    {
        if ($fechaUltimaGestion === null) {
            return false;
        }

        $limite = new DateTimeImmutable("-{$dias} days");

        return new DateTimeImmutable($fechaUltimaGestion) >= $limite;
    }
}
