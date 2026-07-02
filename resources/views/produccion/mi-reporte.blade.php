<x-app-layout>
    {{-- Pantalla de operario: sin banda de cabecera para aprovechar el alto del móvil.
         Título y fecha en una sola línea compacta. --}}
    <div class="py-4 sm:py-8">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <x-secondary-link :href="route('produccion.mi.index')" class="mb-3 inline-flex items-center gap-1">
                <span aria-hidden="true">&larr;</span> Mis producciones
            </x-secondary-link>
            <div class="mb-3 flex items-baseline justify-between gap-3">
                <h2 class="text-lg font-semibold leading-tight text-neutral-900">Mi producción</h2>
                <p class="text-xs text-neutral-500">{{ ($reporte?->fecha ?? now())->translatedFormat('l d \\d\\e F') }}</p>
            </div>

            <x-status-alert :status="session('status')" class="mb-4" />

            <x-produccion.indicador-red />

            {{-- Reportes devueltos pendientes (de otros días/turnos) --}}
            @if ($devueltos->isNotEmpty())
                <div class="dg-enter mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <p class="font-medium">Tienes {{ $devueltos->count() === 1 ? 'un reporte devuelto' : 'reportes devueltos' }} por corregir:</p>
                    <ul class="mt-1 space-y-0.5">
                        @foreach ($devueltos as $devuelto)
                            <li>
                                <a href="{{ route('produccion.mi.show', $devuelto) }}" class="font-medium underline underline-offset-2">
                                    {{ $devuelto->fecha->translatedFormat('d \\d\\e F') }} · turno {{ $devuelto->turno }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! $reporte)
                {{-- Sin asignación --}}
                <div class="dg-enter rounded-2xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
                    <p class="text-sm text-neutral-500">No tienes una asignación de producción para hoy.</p>
                    <p class="mt-1 text-xs text-neutral-400">El jefe de bodega debe asignarte preformas antes de poder reportar.</p>
                </div>
            @elseif (! $reporte->editablePorSoplador())
                {{-- Enviado / aprobado: solo lectura --}}
                <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">Lo que reportaste</h3>
                        <x-produccion.estado-badge :estado="$reporte->estado" />
                    </div>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 p-4 sm:p-6">
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Asignadas</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->asignadas }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Total</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->total }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Primera</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->primera }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Segunda</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->segunda }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Malos</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->malo }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Preforma dañada</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->danada }}</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Tasa de primera</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->tasa_primera }}%</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Tasa de segunda</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->tasa_segunda }}%</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Tasa de malas</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->tasa_malo }}%</dd></div>
                        <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Tasa de dañadas</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->tasa_danada }}%</dd></div>
                    </dl>

                    @if ($reporte->registros->isNotEmpty())
                        <div class="border-t border-neutral-100">
                            <h3 class="px-4 pt-3 text-xs font-medium uppercase tracking-wide text-neutral-500 sm:px-6">Detalle por máquina y tipo</h3>
                            <ul class="divide-y divide-neutral-100">
                                @foreach ($reporte->registros as $registro)
                                    @php
                                        $partes = array_filter([$registro->tipoBotellon?->nombre, $registro->maquina?->nombre]);
                                    @endphp
                                    <li class="px-4 py-3 sm:px-6">
                                        <p class="truncate text-sm font-medium text-neutral-900">{{ $partes ? implode(' · ', $partes) : 'Registro inicial' }}</p>
                                        <p class="text-xs text-neutral-500">1ª {{ $registro->primera }} · 2ª {{ $registro->segunda }} · malos {{ $registro->malo }} · dañadas {{ $registro->danada }} · {{ $registro->created_at->format('H:i') }}</p>
                                        @php
                                            $motivosTanda = collect(['2ª' => $registro->motivo_segunda, 'Malas' => $registro->motivo_malo])
                                                ->filter()->map(fn ($m, $k) => "$k: $m")->implode(' · ');
                                        @endphp
                                        @if ($motivosTanda)
                                            <p class="text-xs text-neutral-400">{{ $motivosTanda }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <p class="border-t border-neutral-100 px-4 py-4 text-xs text-neutral-500 sm:px-6">
                        El reporte enviado no se puede editar. Si hubo un error, el jefe puede devolvértelo.
                    </p>
                </div>
            @else
                {{-- Editable: borrador o devuelto --}}
                @if ($reporte->estado === \App\Models\ProduccionReporte::DEVUELTO && $reporte->devuelto_motivo)
                    <div class="dg-enter mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <span class="font-medium">El jefe te devolvió el reporte:</span> {{ $reporte->devuelto_motivo }}
                    </div>
                @endif

                @php
                    $multiSucursal = $maquinas->pluck('sucursal_id')->unique()->count() > 1;
                    $etiquetasMaquinas = $maquinas->mapWithKeys(fn ($m) => [$m->id => $multiSucursal ? "{$m->nombre} · {$m->sucursal->nombre}" : $m->nombre]);
                    $etiquetasTipos = $tipos->pluck('nombre', 'id');
                @endphp

                <div class="dg-enter overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm"
                     x-data="{
                        primera: {{ (int) old('primera', 0) }},
                        segunda: {{ (int) old('segunda', 0) }},
                        malo: {{ (int) old('malo', 0) }},
                        danada: {{ (int) old('danada', 0) }},
                        obs: {{ Js::from(old('obs', $reporte->obs) ?? '') }},
                        guardado: {{ (int) $reporte->total }},
                        guardadoVendible: {{ (int) $reporte->producido }},
                        asignadas: {{ (int) $reporte->asignadas }},
                        maquinaId: '{{ $maquinaPreseleccionada ?: '' }}',
                        tipoId: '{{ $tipoPreseleccionado ?: '' }}',
                        maquinas: {{ Js::from($etiquetasMaquinas) }},
                        tipos: {{ Js::from($etiquetasTipos) }},
                        paneles: {
                            maquina: {{ $errors->has('maquina_id') ? 'true' : 'false' }},
                            tipo: {{ $errors->has('tipo_botellon_id') ? 'true' : 'false' }},
                            motivo: {{ $errors->has('motivo') ? 'true' : 'false' }},
                            obs: {{ $errors->has('obs') ? 'true' : 'false' }},
                        },
                        agregando: false,
                        avisoTanda: false,
                        get tanda() { return (Number(this.primera) || 0) + (Number(this.segunda) || 0) + (Number(this.malo) || 0) + (Number(this.danada) || 0); },
                        get total() { return this.guardado + this.tanda; },
                        get vendible() { return this.guardadoVendible + (Number(this.primera) || 0) + (Number(this.segunda) || 0); },
                        get diferencia() { return this.asignadas - this.total; },
                        /* Señalar en vez de narrar: antes de mandar al servidor, si falta una
                           precondición abrimos su panel y sacudimos ESE control (sin recargar).
                           El servidor sigue validando igual como respaldo. */
                        agregarTanda(e) {
                            if (this.$refs.grupoMaquina && ! this.maquinaId) { e.preventDefault(); this.paneles.maquina = true; this.$nextTick(() => this.$destacar(this.$refs.grupoMaquina)); return; }
                            if (this.$refs.grupoTipo && ! this.tipoId) { e.preventDefault(); this.paneles.tipo = true; this.$nextTick(() => this.$destacar(this.$refs.grupoTipo)); return; }
                            if (this.segunda > 0 && ! this.$refs.grupoMotivoSegunda.querySelector('input[type=radio]:checked')) { e.preventDefault(); this.$destacar(this.$refs.grupoMotivoSegunda); return; }
                            if (this.malo > 0 && ! this.$refs.grupoMotivoMalo.querySelector('input[type=radio]:checked')) { e.preventDefault(); this.$destacar(this.$refs.grupoMotivoMalo); return; }
                            this.agregando = true;
                        },
                        enviar(e) {
                            if (this.tanda > 0) { e.preventDefault(); this.avisoTanda = true; this.$destacar(this.$refs.grupoTanda); return; }
                            this.avisoTanda = false;
                            const cont = this.$refs.grupoMotivoDiferencia;
                            const sel = cont && cont.querySelector('input[type=radio]:checked');
                            const otro = cont && cont.querySelector('input[name=motivo_otro]');
                            const falta = ! sel || (sel.value === @js(\App\Models\ProduccionReporte::MOTIVO_OTRO) && ! (otro && otro.value.trim()));
                            if (this.diferencia !== 0 && falta) { e.preventDefault(); this.paneles.motivo = true; this.$nextTick(() => this.$destacar(cont)); return; }
                            if (! confirm('¿Enviar el reporte? No podrás editarlo después.')) e.preventDefault();
                        }
                     }">
                    {{-- La asignación, siempre a la vista --}}
                    <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-3 sm:px-6">
                        <span class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                            Preformas asignadas{{ $reporte->fecha->isToday() ? ' hoy' : '' }}
                        </span>
                        <span class="text-xl font-bold text-neutral-900">{{ $reporte->asignadas }}</span>
                    </div>

                    {{-- Agregar una tanda: máquina + tipo + cantidades --}}
                    <form method="POST" action="{{ route('produccion.mi.registros.store', $reporte) }}"
                          class="space-y-4 p-4 sm:p-6" x-on:submit="agregarTanda($event)">
                        @csrf

                        @if ($maquinas->isNotEmpty())
                            <x-collapsible label="Máquina" model="paneles.maquina" x-ref="grupoMaquina">
                                <x-slot:summary><span x-text="maquinas[maquinaId] || 'Toca para elegir'"></span></x-slot:summary>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    @foreach ($maquinas as $maquina)
                                        <x-chip-radio name="maquina_id" :value="$maquina->id"
                                                      :label="$etiquetasMaquinas[$maquina->id]"
                                                      :checked="(int) old('maquina_id', $maquinaPreseleccionada) === $maquina->id"
                                                      x-model="maquinaId" x-on:change="paneles.maquina = false" />
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('maquina_id')" class="mt-2" />
                            </x-collapsible>
                        @endif

                        @if ($tipos->isNotEmpty())
                            <x-collapsible label="Tipo de botellón" model="paneles.tipo" x-ref="grupoTipo">
                                <x-slot:summary><span x-text="tipos[tipoId] || 'Toca para elegir'"></span></x-slot:summary>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ($tipos as $tipo)
                                        <x-chip-radio name="tipo_botellon_id" :value="$tipo->id" :label="$tipo->nombre"
                                                      :checked="(int) old('tipo_botellon_id', $tipoPreseleccionado) === $tipo->id"
                                                      x-model="tipoId" x-on:change="paneles.tipo = false" />
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('tipo_botellon_id')" class="mt-2" />
                            </x-collapsible>
                        @endif

                        <x-stepper-input name="primera" label="Primera" hint="Vendible normal." :value="old('primera', 0)" />

                        <div>
                            <x-stepper-input name="segunda" label="Segunda" hint="Defecto leve." :value="old('segunda', 0)" />
                            <div x-show="segunda > 0" x-cloak class="mt-2" x-ref="grupoMotivoSegunda">
                                <x-reason-chips name="motivo_segunda" label="Motivo de las de segunda"
                                                :options="\App\Models\ProduccionRegistro::MOTIVOS_DEFECTO"
                                                :selected="old('motivo_segunda')" />
                            </div>
                        </div>

                        <div>
                            <x-stepper-input name="malo" label="Malos" hint="No vendible · reciclaje." :value="old('malo', 0)" />
                            <div x-show="malo > 0" x-cloak class="mt-2" x-ref="grupoMotivoMalo">
                                <x-reason-chips name="motivo_malo" label="Motivo de las malas"
                                                :options="\App\Models\ProduccionRegistro::MOTIVOS_DEFECTO"
                                                :selected="old('motivo_malo')" />
                            </div>
                        </div>

                        <x-stepper-input name="danada" label="Preforma dañada" hint="Se rompió antes de soplar." :value="old('danada', 0)" />

                        <x-primary-button class="h-12 w-full" x-ref="grupoTanda" x-bind:disabled="agregando || tanda === 0">
                            Agregar al reporte
                        </x-primary-button>
                    </form>

                    {{-- Tandas registradas --}}
                    @if ($reporte->registros->isNotEmpty())
                        <div class="border-t border-neutral-100">
                            <div class="flex items-center justify-between px-4 pt-3 sm:px-6">
                                <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                                    {{ $reporte->fecha->isToday() ? 'Hoy llevas' : 'Registrado' }}
                                </h3>
                                <span class="text-xs font-medium text-neutral-400">
                                    {{ $reporte->registros->count() }} {{ \Illuminate\Support\Str::plural('registro', $reporte->registros->count()) }}
                                </span>
                            </div>
                            <ul class="divide-y divide-neutral-100">
                                @foreach ($reporte->registros as $registro)
                                    @php
                                        $partes = array_filter([$registro->tipoBotellon?->nombre, $registro->maquina?->nombre]);
                                    @endphp
                                    <li class="flex items-center gap-3 px-4 py-3 sm:px-6">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-neutral-900">{{ $partes ? implode(' · ', $partes) : 'Registro inicial' }}</p>
                                            <p class="text-xs text-neutral-500">1ª {{ $registro->primera }} · 2ª {{ $registro->segunda }} · malos {{ $registro->malo }} · dañadas {{ $registro->danada }} · {{ $registro->created_at->format('H:i') }}</p>
                                        @php
                                            $motivosTanda = collect(['2ª' => $registro->motivo_segunda, 'Malas' => $registro->motivo_malo])
                                                ->filter()->map(fn ($m, $k) => "$k: $m")->implode(' · ');
                                        @endphp
                                        @if ($motivosTanda)
                                            <p class="text-xs text-neutral-400">{{ $motivosTanda }}</p>
                                        @endif
                                        </div>
                                        <form method="POST" action="{{ route('produccion.mi.registros.destroy', [$reporte, $registro]) }}"
                                              onsubmit="return confirm('¿Eliminar este registro?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-button type="submit" variant="danger" label="Eliminar registro" title="Eliminar registro">
                                                <x-icon.trash class="h-5 w-5" />
                                            </x-icon-button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Resumen en vivo --}}
                    <div class="border-t border-neutral-100 bg-neutral-50 px-4 py-3 text-sm sm:px-6">
                        <div class="flex items-center justify-between">
                            <span class="text-neutral-500">Total ingresado</span>
                            <span class="text-base font-semibold text-neutral-900" x-text="total">{{ $reporte->total }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between">
                            <span class="text-neutral-500">Vendible (1ª+2ª)</span>
                            <span class="font-semibold text-brand-600" x-text="vendible">{{ $reporte->producido }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between" x-show="tanda > 0" x-cloak>
                            <span class="text-brand-600">Tanda sin agregar</span>
                            <span class="font-semibold text-brand-600" x-text="tanda"></span>
                        </div>
                        <div class="mt-1 flex items-center justify-between">
                            <span class="text-neutral-500">Diferencia con asignadas</span>
                            <span class="text-base font-semibold" :class="diferencia === 0 ? 'text-neutral-400' : 'text-neutral-900'" x-text="diferencia">{{ $reporte->diferencia }}</span>
                        </div>
                    </div>

                    {{-- Enviar el reporte --}}
                    <form method="POST" action="{{ route('produccion.mi.update', $reporte) }}"
                          class="space-y-4 border-t border-neutral-100 p-4 sm:p-6"
                          x-on:submit="enviar($event)">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="enviar" value="1">

                        {{-- Motivo: requerido si hay diferencia; chips tocables + "Otro" --}}
                        <div x-show="diferencia !== 0" @if($reporte->diferencia === 0) x-cloak @endif x-ref="grupoMotivoDiferencia">
                            <x-collapsible label="Motivo de la diferencia" model="paneles.motivo">
                                <x-slot:summary>¿Por qué no cuadra con lo asignado?</x-slot:summary>
                                <x-reason-chips name="motivo" allow-other
                                                :options="\App\Models\ProduccionReporte::MOTIVOS_DIFERENCIA"
                                                :selected="old('motivo', $reporte->motivo)" />
                            </x-collapsible>
                        </div>

                        <x-collapsible label="Observaciones (opcional)" model="paneles.obs">
                            <x-slot:summary><span x-text="obs ? obs : 'Toca para agregar una nota'"></span></x-slot:summary>
                            <p class="text-xs text-neutral-500">Toca una nota o escribe.</p>
                            <div class="mt-1.5 flex flex-wrap gap-2">
                                @foreach (\App\Models\ProduccionReporte::NOTAS_COMUNES as $nota)
                                    <button type="button"
                                            x-on:click="obs = obs.includes(@js($nota)) ? obs : (obs.trim() ? obs.trim() + ' · ' : '') + @js($nota)"
                                            class="inline-flex min-h-11 items-center rounded-full border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-600 shadow-sm transition duration-150 hover:bg-neutral-50 active:scale-[0.98]">
                                        <span aria-hidden="true" class="mr-1 text-brand-600">+</span>{{ $nota }}
                                    </button>
                                @endforeach
                            </div>
                            <x-textarea id="obs" name="obs" rows="2" class="mt-2" x-model="obs">{{ old('obs', $reporte->obs) }}</x-textarea>
                            <x-input-error :messages="$errors->get('obs')" class="mt-2" />
                        </x-collapsible>

                        <p x-show="avisoTanda" x-cloak class="dg-shake rounded-lg bg-brand-50 px-3.5 py-2.5 text-sm font-medium text-brand-700">
                            Tienes una tanda sin agregar. Toca «Agregar al reporte» antes de enviar.
                        </p>
                        <x-input-error :messages="$errors->get('enviar')" />

                        <div class="flex sm:justify-end">
                            <x-primary-button class="h-12 w-full sm:h-auto sm:w-auto">
                                Confirmar y enviar
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
