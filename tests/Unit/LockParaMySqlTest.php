<?php

namespace Tests\Unit;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\Query\Processors\Processor;
use PHPUnit\Framework\TestCase;

/**
 * Cobertura HONESTA del `lockForUpdate` de los despachos (hallazgo 1 del gate del
 * Director, 28-07).
 *
 * El problema que resuelve este archivo: `SQLiteGrammar::compileLock()` devuelve
 * `''` de forma INCONDICIONAL, así que bajo la BD de la suite (SQLite en memoria)
 * `->lockForUpdate()` produce SQL byte-idéntico a no llamarlo. Ningún test de
 * feature puede probar el lock — ni siquiera espiando con `DB::listen`, porque el
 * SQL que pasa por ahí tampoco lo lleva. Durante P-DSP-04 declaré "mutación
 * verificada" sobre el lock; lo que aquel test probaba era la RE-LECTURA.
 *
 * Acá se prueba lo que sí se puede probar sin una BD MySQL: que el mismo builder
 * que usa `DespachoService`, compilado con la grammar de PRODUCCIÓN, emite el
 * `for update`. Es un test de unidad puro (sin BD, sin framework booteado).
 */
class LockParaMySqlTest extends TestCase
{
    private function builder(string $grammar): Builder
    {
        // En Laravel 12 la grammar recibe la conexión en el constructor.
        $conexion = $this->createMock(Connection::class);

        return new Builder($conexion, new $grammar($conexion), new Processor);
    }

    public function test_el_builder_con_lock_emite_for_update_en_mysql(): void
    {
        $sql = $this->builder(MySqlGrammar::class)
            ->from('despachos')
            ->where('id', 1)
            ->lockForUpdate()
            ->toSql();

        $this->assertStringEndsWith('for update', $sql,
            'El lock de fila debe llegar al SQL en producción (MySQL): es la mitad '
            .'que protege contra dos procesos PHP concurrentes, donde la re-lectura sola no basta.');
    }

    /**
     * El folio correlativo de la hoja de ruta (P-DSP-08) calcula max(folio)+1
     * con el MISMO idioma: este es su builder, compilado con la grammar de
     * producción. En MySQL el `for update` sobre el agregado toma el next-key
     * lock que serializa dos creaciones simultáneas; la red final es el
     * unique de `hojas_de_ruta.folio` (HojaRutaTest lo cubre a nivel BD).
     */
    public function test_el_max_del_folio_con_lock_emite_for_update_en_mysql(): void
    {
        $sql = $this->builder(MySqlGrammar::class)
            ->from('hojas_de_ruta')
            ->selectRaw('max(folio)')
            ->lockForUpdate()
            ->toSql();

        $this->assertStringEndsWith('for update', $sql);
    }

    /**
     * El contrafáctico que documenta POR QUÉ el lock no se puede asertar en la
     * suite de feature. Si algún día este test empieza a fallar es una BUENA
     * noticia: significaría que SQLite pasó a soportar el lock y que entonces sí
     * se podría cubrir en un test de feature.
     */
    public function test_en_sqlite_el_mismo_lock_no_emite_nada(): void
    {
        $conLock = $this->builder(SQLiteGrammar::class)
            ->from('despachos')->where('id', 1)->lockForUpdate()->toSql();

        $sinLock = $this->builder(SQLiteGrammar::class)
            ->from('despachos')->where('id', 1)->toSql();

        $this->assertSame($sinLock, $conLock,
            'Bajo SQLite el lock es un no-op: por eso su cobertura vive en este test de grammar '
            .'y no en RetiroQrTest.');
        $this->assertStringNotContainsString('for update', $conLock);
    }
}
