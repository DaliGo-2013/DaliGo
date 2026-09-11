<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * LOS CRONS DEL «REINTENTO DE MADRUGADA» TIENEN QUE CAER DE MADRUGADA DE VERDAD.
 *
 * Lo que pasó (bitácora 2026-09-11): el workflow declaraba `07:10/08:10/09:10 UTC` —«03:10 a
 * 05:10 de Chile»— y GitHub lo disparó ~5 HORAS TARDE todos los días (12:11, 12:53 y 13:31 UTC
 * el 8, el 9 y el 10 de septiembre). O sea que corría a las 09:10–10:30 de la mañana en Chile,
 * en plena hora de oficina: exactamente el momento en que el tope de 25 procesos del hosting
 * mata el deploy, y exactamente lo que el workflow existe para evitar. El deploy del 10-09
 * amaneció sin desplegar y nadie se había enterado, porque «el servidor ya está al día» también
 * sale `success`.
 *
 * Un `schedule:` de GitHub no es la hora que dice. Este candado no puede fijar la hora REAL de
 * ejecución —eso lo decide GitHub—, pero sí puede fijar las dos cosas que la acercan: hora
 * DECLARADA temprana (con 5 h de retraso sigue cayendo antes de que abra la oficina en Chile)
 * y minuto IMPAR, fuera de los que GitHub encola más. Si alguien vuelve a ponerlos «a las 7 UTC
 * que es de madrugada en Chile», esto se pone rojo y le cuenta por qué.
 */
class ReintentoDeployCronTest extends TestCase
{
    private const WORKFLOW = '.github/workflows/reintento-deploy-madrugada.yml';

    /**
     * Hora declarada tope, en UTC. Con el retraso observado (~5 h), 4 UTC cae a las 09:37 UTC
     * = 06:37 de Chile (UTC-3 en horario de verano; 05:37 en invierno): antes de la oficina.
     */
    private const HORA_UTC_MAXIMA = 4;

    /** Los minutos que GitHub encola más, por ser los que todo el mundo elige. */
    private const MINUTOS_POPULARES = [0, 5, 10, 15, 30, 45];

    /** @return list<array{minuto:int,hora:int}> */
    private function crons(): array
    {
        $yml = file_get_contents(base_path(self::WORKFLOW));
        $this->assertNotFalse($yml, 'No se pudo leer el workflow del reintento.');

        preg_match_all("/^\s*-\s*cron:\s*'(\d+)\s+(\d+)\s+\*\s+\*\s+\*'/m", $yml, $m, PREG_SET_ORDER);

        // Control positivo: si el regex no encuentra ninguno, los asserts de abajo pasarían por
        // vacío. Tres oportunidades es el diseño del workflow («por si una agarra carga»).
        $this->assertCount(3, $m, 'El workflow debería declarar exactamente tres crons diarios.');

        return array_map(fn (array $c) => ['minuto' => (int) $c[1], 'hora' => (int) $c[2]], $m);
    }

    public function test_los_crons_se_declaran_lo_bastante_temprano_para_absorber_el_retraso_de_github(): void
    {
        foreach ($this->crons() as $c) {
            $this->assertLessThanOrEqual(
                self::HORA_UTC_MAXIMA,
                $c['hora'],
                sprintf(
                    'El cron %02d:%02d UTC es tarde: GitHub lo dispara ~5 h después y caería en hora de oficina de Chile, '
                    .'donde el tope de 25 procesos mata el deploy. Máximo %d UTC (ver la cabecera de este test).',
                    $c['hora'], $c['minuto'], self::HORA_UTC_MAXIMA,
                ),
            );
        }
    }

    public function test_los_crons_usan_un_minuto_impopular(): void
    {
        foreach ($this->crons() as $c) {
            $this->assertNotContains(
                $c['minuto'],
                self::MINUTOS_POPULARES,
                sprintf('El minuto :%02d es de los que GitHub encola más; elegí uno raro (37, 43, 51…).', $c['minuto']),
            );
        }
    }

    /** Tres horas DISTINTAS: tres crons a la misma hora son una oportunidad, no tres. */
    public function test_las_tres_oportunidades_son_horas_distintas(): void
    {
        $horas = array_column($this->crons(), 'hora');

        $this->assertCount(3, array_unique($horas), 'Los tres crons tienen que caer en horas distintas.');
    }
}
