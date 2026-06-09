<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Importar catálogo" subtitle="Carga masiva de productos desde un CSV.">
            <x-slot name="action">
                <x-secondary-link :href="route('admin.productos.index')">Volver al catálogo</x-secondary-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Resultado de una importación previa --}}
            @if ($resultado = session('importResult'))
                <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Resultado de la importación</h3>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <x-badge>{{ $resultado['creados'] }} creados</x-badge>
                        <x-badge>{{ $resultado['actualizados'] }} actualizados</x-badge>
                        <x-badge variant="neutral">{{ $resultado['sin_cambios'] ?? 0 }} sin cambios</x-badge>
                        @if (($resultado['vaciados'] ?? 0) > 0)
                            <x-badge variant="neutral">{{ $resultado['vaciados'] }} campos vaciados</x-badge>
                        @endif
                        <x-badge variant="neutral">{{ count($resultado['errores']) }} con error</x-badge>
                    </div>

                    @if (! empty($resultado['errores']))
                        <div class="mt-4 overflow-hidden rounded-lg border border-neutral-200">
                            <table class="min-w-full divide-y divide-neutral-100 text-sm">
                                <thead class="bg-neutral-50 text-left text-xs font-medium uppercase tracking-wide text-neutral-500">
                                    <tr>
                                        <th class="px-4 py-2 w-20">Fila</th>
                                        <th class="px-4 py-2">Error</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100">
                                    @foreach (array_slice($resultado['errores'], 0, 50) as $e)
                                        <tr>
                                            <td class="px-4 py-2 text-neutral-500">{{ $e['fila'] }}</td>
                                            <td class="px-4 py-2 text-neutral-700">{{ $e['error'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if (count($resultado['errores']) > 50)
                                <p class="bg-neutral-50 px-4 py-2 text-xs text-neutral-500">
                                    … y {{ count($resultado['errores']) - 50 }} errores más.
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            {{-- Formulario de carga --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
                <form method="POST" action="{{ route('admin.productos.import') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <div>
                        <x-input-label for="archivo" value="Archivo CSV" />
                        <input id="archivo" name="archivo" type="file" accept=".csv,.txt" required
                               class="mt-1.5 block w-full rounded-lg border border-neutral-300 bg-white text-sm text-neutral-700 shadow-sm file:mr-4 file:border-0 file:bg-neutral-100 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-neutral-700 hover:file:bg-neutral-200 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30" />
                        <x-input-error :messages="$errors->get('archivo')" class="mt-2" />
                    </div>

                    <x-form-footer :cancel="route('admin.productos.index')">
                        <x-primary-button>Importar</x-primary-button>
                    </x-form-footer>
                </form>
            </div>

            {{-- Ayuda de formato --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">
                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Formato esperado</h3>
                <p class="mt-2 text-sm text-neutral-600">
                    Columnas importables (la cabecera define el orden; solo <strong>sku</strong> es obligatoria;
                    <strong>nombre</strong> se exige al crear productos nuevos):
                </p>
                <p class="mt-1 text-xs text-neutral-500">
                    <code>sku · nombre · descripcion · categoria · marca · peso_kg · alto_cm · ancho_cm · largo_cm · activo</code>
                </p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-neutral-600">
                    <li>Se actualiza por <strong>SKU</strong> (reimportar el mismo archivo actualiza, no duplica).</li>
                    <li><strong>Solo se tocan las columnas presentes en el archivo</strong>: una columna ausente no se modifica; una <strong>celda vacía borra</strong> el valor de ese campo.</li>
                    <li>Separador <code>;</code> o <code>,</code> (se detecta solo). Decimales con coma o punto.</li>
                    <li>Las filas con error se saltan y se listan; las válidas se cargan igual.</li>
                    <li>Las columnas de Bsale (<code>barcode</code>, <code>bsale_*</code>) y cualquier columna desconocida se <strong>ignoran</strong> al importar (vienen en el export solo como referencia).</li>
                    <li>Para cargar <strong>peso y dimensiones</strong>: usa la «Plantilla de medidas» del catálogo — trae los SKUs pendientes con columnas de referencia y solo escribe las medidas al reimportarla.</li>
                </ul>
                <div class="mt-4 flex flex-wrap gap-3">
                    <x-secondary-link :href="route('admin.productos.template')">Descargar plantilla CSV</x-secondary-link>
                    <x-secondary-link :href="route('admin.productos.plantilla.medidas')">Descargar plantilla de medidas</x-secondary-link>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
