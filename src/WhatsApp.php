<?php
declare(strict_types=1);

final class WhatsApp
{
    public const PLANTILLAS = [
        1 => [
            'titulo' => 'Recordatorio',
            'texto'  => 'Hola {nombre}, te contactamos de Imperio Comercial. Registramos {cuotas} cuota(s) pendiente(s). ¿Podemos coordinar el pago?',
        ],
        2 => [
            'titulo' => 'Agenda vencida',
            'texto'  => 'Hola {nombre}, quedamos en que abonabas el {fecha}. ¿Pudiste realizar el pago?',
        ],
        3 => [
            'titulo' => 'Refinanciación',
            'texto'  => 'Hola {nombre}, tenemos una propuesta para que te pongas al día con cuotas más cómodas. ¿Te interesa?',
        ],
        4 => [
            'titulo' => 'Recordatorio de agenda de hoy',
            'texto'  => 'Hola {nombre}, te recordamos que hoy quedamos en que abonabas ${monto}. ¿Confirmás?',
        ],
    ];

    /**
     * Plantillas 2 (agenda vencida) y 4 (agenda de hoy) solo se ofrecen si
     * el ticket tiene una agenda que las justifique; el resto siempre está
     * disponible. $agendas viene de Agendas::paraTicket().
     *
     * @return array<int, array{id: int, titulo: string, texto: string}>
     */
    public static function plantillasDisponibles(string $nombre, int $cuotasVencidas, array $agendas): array
    {
        $variables = array_merge(
            ['nombre' => $nombre, 'cuotas' => $cuotasVencidas],
            self::variablesDesdeAgendas($agendas)
        );

        $disponibles = [];
        foreach (self::PLANTILLAS as $id => $plantilla) {
            if ($id === 2 && !isset($variables['fecha'])) {
                continue;
            }
            if ($id === 4 && !isset($variables['monto'])) {
                continue;
            }

            $disponibles[] = [
                'id'     => $id,
                'titulo' => $plantilla['titulo'],
                'texto'  => self::renderizarPlantilla($id, $variables),
            ];
        }

        return $disponibles;
    }

    /**
     * De todas las agendas de un ticket, extrae {fecha} (la más relevante
     * vencida/incumplida) y {monto} (si hay una agenda pendiente para HOY).
     * Antes de que exista cron_diario.php (Fase 3), una agenda vencida
     * sigue en estado 'pendiente' con fecha pasada, así que se contempla
     * ese caso además de 'incumplida'.
     */
    public static function variablesDesdeAgendas(array $agendas): array
    {
        $hoy       = (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d');
        $variables = [];

        foreach ($agendas as $agenda) {
            if ($agenda['estado'] === 'pendiente' && $agenda['fecha_agendada'] === $hoy) {
                $variables['monto'] = number_format((float) $agenda['monto_esperado'], 0, ',', '.');
            }

            $esVencidaSinResolver = $agenda['estado'] === 'incumplida'
                || ($agenda['estado'] === 'pendiente' && $agenda['fecha_agendada'] < $hoy);

            if ($esVencidaSinResolver && !isset($variables['fecha'])) {
                $variables['fecha'] = (new DateTimeImmutable($agenda['fecha_agendada']))->format('d/m/Y');
            }
        }

        return $variables;
    }

    public static function renderizarPlantilla(int $idPlantilla, array $variables): ?string
    {
        if (!isset(self::PLANTILLAS[$idPlantilla])) {
            return null;
        }

        $texto = self::PLANTILLAS[$idPlantilla]['texto'];
        foreach ($variables as $clave => $valor) {
            $texto = str_replace('{' . $clave . '}', (string) $valor, $texto);
        }

        return $texto;
    }

    /**
     * Números argentinos guardados como 10 dígitos limpios (confirmado
     * contra datos reales de SAS), pero se limpian igual 0/15/guiones por
     * las dudas si en el futuro cambia el origen del dato.
     */
    public static function normalizarTelefono(string $telefono): string
    {
        $soloDigitos = preg_replace('/\D/', '', $telefono) ?? '';
        $soloDigitos = preg_replace('/^0/', '', $soloDigitos) ?? $soloDigitos;
        $soloDigitos = preg_replace('/^(\d{2,4})15/', '$1', $soloDigitos) ?? $soloDigitos;

        return '549' . $soloDigitos;
    }

    public static function construirLink(string $telefono, string $mensaje): string
    {
        return 'https://wa.me/' . self::normalizarTelefono($telefono) . '?text=' . rawurlencode($mensaje);
    }
}
