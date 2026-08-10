<x-app-layout ancho="formulario">
    {{-- Pantalla de operario: sin banda de cabecera para aprovechar el alto del móvil.
         Título y fecha en una sola línea compacta. --}}
    <div class="py-4 sm:py-8">
        {{-- El <x-volver> va suelto en el cuerpo (no en un page-header): esta
             pantalla omite la banda de cabecera a propósito. Misma posición
             relativa —antes del título— y mismo pixel que en el resto de la app. --}}
        <x-volver :href="route('produccion.mi.index')" titulo="Volver a Mis producciones" class="mb-3" />
        <div class="mb-3 flex items-baseline justify-between gap-3">
            <h2 class="text-lg font-semibold leading-tight text-neutral-900">Mi producción</h2>
            <p class="text-xs text-neutral-500">{{ ($reporte?->fecha ?? \App\Support\FechaNegocio::ahora())->translatedFormat('l d \\d\\e F') }}</p>
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
                    <div><dt class="text-xs uppercase tracking-wide text-neutral-400">Cavidades activas</dt><dd class="mt-1 text-sm font-medium text-neutral-900">{{ $reporte->cavidades_activas ?? 'Todas' }}</dd></div>
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
                                    <p class="text-xs text-neutral-500">1ª {{ $registro->primera }} · 2ª {{ $registro->segunda }} · malos {{ $registro->malo }} · dañadas {{ $registro->danada }} · {{ $registro->created_at->enChile()->format('H:i') }}</p>
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

                @if ($reporte->paradas->isNotEmpty())
                    <div class="border-t border-neutral-100">
                        <h3 class="px-4 pt-3 text-xs font-medium uppercase tracking-wide text-neutral-500 sm:px-6">Paradas del turno</h3>
                        <ul class="divide-y divide-neutral-100">
                            @foreach ($reporte->paradas as $parada)
                                <li class="px-4 py-3 sm:px-6">
                                    <p class="truncate text-sm font-medium text-neutral-900">{{ $parada->motivo }}{{ $parada->maquina ? ' · '.$parada->maquina->nombre : '' }}</p>
                                    <p class="text-xs text-neutral-500">
                                        {{ $parada->inicio_corta }} a {{ $parada->fin_corta ?? 'sin término' }}{{ $parada->duracion_label ? ' · '.$parada->duracion_label : '' }}
                                        @if ($parada->cerrada_al_envio)
                                            · cerrada al envío
                                        @endif
                                    </p>
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
                    /* Estado PROPIO de la parada (no reusar maquinaId: un x-model
                       compartido sincronizaría los chips de la tanda con los de la
                       parada). Los campos van prefijados parada_* por lo mismo. */
                    paradaMaquinaId: '{{ old('parada_maquina_id', $maquinaPreseleccionada ?: '') }}',
                    paradaInicio: '{{ old('parada_inicio', '') }}',
                    paradaFin: '{{ old('parada_fin', '') }}',
                    registrandoParada: false,
                    cavidades: '{{ old('cavidades_activas', $reporte->cavidades_activas ?? '') }}',
                    paneles: {
                        maquina: {{ $errors->has('maquina_id') ? 'true' : 'false' }},
                        tipo: {{ $errors->has('tipo_botellon_id') ? 'true' : 'false' }},
                        motivo: {{ $errors->has('motivo') ? 'true' : 'false' }},
                        obs: {{ $errors->has('obs') ? 'true' : 'false' }},
                        paradas: {{ $errors->hasAny(['parada_motivo', 'parada_origen', 'parada_inicio', 'parada_fin', 'parada_maquina_id']) ? 'true' : 'false' }},
                        cavidades: {{ $errors->has('cavidades_activas') ? 'true' : 'false' }},
                    },
                    agregando: false,
                    avisoTanda: false,
                    pendientesOffline: 0,
                    init() {
                        /* Contador de tandas guardadas sin conexión (spike P-SPK-02). */
                        if (window.dgCola) window.dgCola.pendientes().then((n) => { this.pendientesOffline = n; });
                        window.addEventListener('daligo:cola-cambio', async () => {
                            if (window.dgCola) this.pendientesOffline = await window.dgCola.pendientes();
                        });
                    },
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
                        /* Sin señal: guardar la tanda en la cola local en vez de enviarla; se
                           sincroniza sola al volver la conexión (spike P-SPK-02). Con señal,
                           deja pasar el submit nativo de siempre. */
                        if (window.dgCola && this.$store.red && ! this.$store.red.online) {
                            e.preventDefault();
                            this.guardarOffline(e.target);
                            return;
                        }
                        this.agregando = true;
                    },
                    async guardarOffline(form) {
                        const fd = new FormData(form);
                        fd.delete('_token'); /* el token se lee fresco al drenar; no encolar uno stale */
                        const campos = Object.fromEntries(fd.entries());
                        const uuid = (crypto.randomUUID && crypto.randomUUID()) || (Date.now() + '-' + Math.random());
                        /* Acumular optimísticamente ANTES de resetear (el reload al reconectar
                           reconcilia con los registros reales del servidor). */
                        this.guardadoVendible += (Number(this.primera) || 0) + (Number(this.segunda) || 0);
                        this.guardado += this.tanda;
                        await window.dgCola.encolar({ uuid, url: form.action, campos });
                        this.primera = 0; this.segunda = 0; this.malo = 0; this.danada = 0;
                        this.pendientesOffline = await window.dgCola.pendientes();
                    },
                    /* Parada del turno (P-M11-20): mismas guardas señalar-no-narrar y
                       misma bifurcación offline que la tanda; el form es hermano y
                       viaja por la MISMA cola (window.dgCola). */
                    agregarParada(e) {
                        const form = e.target;
                        if (! form.querySelector('input[name=parada_motivo]:checked')) { e.preventDefault(); this.paneles.paradas = true; this.$nextTick(() => this.$destacar(this.$refs.grupoParadaMotivo)); return; }
                        if (! this.paradaInicio) { e.preventDefault(); this.$destacar(this.$refs.grupoParadaHoras); return; }
                        /* Cortesía en cliente; el servidor valida igual (after_or_equal).
                           Comparación lexicográfica válida para "HH:MM" con cero inicial. */
                        if (this.paradaFin && this.paradaFin < this.paradaInicio) { e.preventDefault(); this.$destacar(this.$refs.grupoParadaHoras); return; }
                        if (window.dgCola && this.$store.red && ! this.$store.red.online) {
                            e.preventDefault();
                            this.guardarParadaOffline(form);
                            return;
                        }
                        this.registrandoParada = true;
                    },
                    async guardarParadaOffline(form) {
                        const fd = new FormData(form);
                        fd.delete('_token'); /* el token se lee fresco al drenar; no encolar uno stale */
                        const campos = Object.fromEntries(fd.entries());
                        const uuid = (crypto.randomUUID && crypto.randomUUID()) || (Date.now() + '-' + Math.random());
                        await window.dgCola.encolar({ uuid, url: form.action, campos });
                        this.paradaInicio = ''; this.paradaFin = '';
                        this.pendientesOffline = await window.dgCola.pendientes();
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
                        Preformas asignadas{{ \App\Support\FechaNegocio::esHoy($reporte->fecha) ? ' hoy' : '' }}
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

                    {{-- Tandas guardadas sin conexión, pendientes de enviar (spike P-SPK-02). --}}
                    <p x-show="pendientesOffline > 0" x-cloak
                       class="flex items-center gap-2 rounded-lg bg-neutral-100 px-3 py-2 text-xs font-medium text-neutral-600">
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-neutral-400" aria-hidden="true"></span>
                        <span><span x-text="pendientesOffline"></span> guardada(s) sin conexión — se enviarán solas al volver la señal.</span>
                    </p>
                </form>

                {{-- Tandas registradas --}}
                @if ($reporte->registros->isNotEmpty())
                    <div class="border-t border-neutral-100">
                        <div class="flex items-center justify-between px-4 pt-3 sm:px-6">
                            <h3 class="text-xs font-medium uppercase tracking-wide text-neutral-500">
                                {{ \App\Support\FechaNegocio::esHoy($reporte->fecha) ? 'Hoy llevas' : 'Registrado' }}
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
                                        <p class="text-xs text-neutral-500">1ª {{ $registro->primera }} · 2ª {{ $registro->segunda }} · malos {{ $registro->malo }} · dañadas {{ $registro->danada }} · {{ $registro->created_at->enChile()->format('H:i') }}</p>
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

                {{-- Paradas del turno (P-M11-20): qué detuvo la producción + horas.
                     Form HERMANO del de la tanda (jamás anidado); campos prefijados
                     parada_* para no contaminar old() de los otros forms. --}}
                <div class="border-t border-neutral-100 p-4 sm:p-6">
                    <x-collapsible label="Paradas del turno" model="paneles.paradas">
                        <x-slot:summary>
                            {{ $reporte->paradas->isNotEmpty()
                                ? $reporte->paradas->count().' '.\Illuminate\Support\Str::plural('registrada', $reporte->paradas->count())
                                : '¿Se detuvo la producción? Regístralo aquí' }}
                        </x-slot:summary>
                        <form method="POST" action="{{ route('produccion.mi.paradas.store', $reporte) }}"
                              class="space-y-4" x-on:submit="agregarParada($event)">
                            @csrf

                            <div x-ref="grupoParadaMotivo">
                                <x-reason-chips name="parada_motivo" label="¿Qué detuvo la producción?"
                                                :options="\App\Models\ProduccionParada::MOTIVOS"
                                                :selected="old('parada_motivo')" />
                            </div>

                            <div>
                                <x-input-label value="¿Qué se detuvo?" />
                                <div class="mt-1.5 grid grid-cols-2 gap-2">
                                    <x-chip-radio name="parada_origen" value="maquina" label="La máquina"
                                                  :checked="old('parada_origen', 'maquina') === 'maquina'" />
                                    <x-chip-radio name="parada_origen" value="operario" label="El operario"
                                                  :checked="old('parada_origen') === 'operario'" />
                                </div>
                                <x-input-error :messages="$errors->get('parada_origen')" class="mt-2" />
                            </div>

                            @if ($maquinas->isNotEmpty())
                                <div>
                                    <x-input-label value="Máquina" />
                                    <div class="mt-1.5 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        @foreach ($maquinas as $maquina)
                                            <x-chip-radio name="parada_maquina_id" :value="$maquina->id"
                                                          :label="$etiquetasMaquinas[$maquina->id]"
                                                          :checked="(string) old('parada_maquina_id', $maquinaPreseleccionada ?: '') === (string) $maquina->id"
                                                          x-model="paradaMaquinaId" />
                                        @endforeach
                                    </div>
                                    <x-input-error :messages="$errors->get('parada_maquina_id')" class="mt-2" />
                                </div>
                            @endif

                            <div x-ref="grupoParadaHoras" class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-input-label for="parada_inicio" value="Empezó" />
                                    <x-text-input id="parada_inicio" name="parada_inicio" type="time"
                                                  class="mt-1.5 h-12 w-full" x-model="paradaInicio" required />
                                    <x-input-error :messages="$errors->get('parada_inicio')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="parada_fin" value="Terminó" />
                                    <x-text-input id="parada_fin" name="parada_fin" type="time"
                                                  class="mt-1.5 h-12 w-full" x-model="paradaFin" />
                                    <x-input-hint>Déjala abierta si sigue detenida.</x-input-hint>
                                    <x-input-error :messages="$errors->get('parada_fin')" class="mt-2" />
                                </div>
                            </div>

                            <x-secondary-button type="submit" class="h-12 w-full justify-center"
                                                x-bind:disabled="registrandoParada">
                                Registrar parada
                            </x-secondary-button>
                        </form>
                    </x-collapsible>

                    @if ($reporte->paradas->isNotEmpty())
                        <ul class="mt-3 divide-y divide-neutral-100 rounded-lg border border-neutral-200">
                            @foreach ($reporte->paradas as $parada)
                                <li class="flex items-center gap-3 px-3 py-2.5">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-neutral-900">{{ $parada->motivo }}{{ $parada->maquina ? ' · '.$parada->maquina->nombre : '' }}</p>
                                        <p class="text-xs text-neutral-500">
                                            {{ $parada->inicio_corta }} a {{ $parada->fin_corta ?? 'en curso' }}{{ $parada->duracion_label ? ' · '.$parada->duracion_label : '' }}
                                            @if ($parada->cerrada_al_envio)
                                                · cerrada al envío
                                            @endif
                                        </p>
                                    </div>
                                    <form method="POST" action="{{ route('produccion.mi.paradas.destroy', [$reporte, $parada]) }}"
                                          onsubmit="return confirm('¿Eliminar esta parada?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-button type="submit" variant="danger" label="Eliminar parada" title="Eliminar parada">
                                            <x-icon.trash class="h-5 w-5" />
                                        </x-icon-button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

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

                    <x-collapsible label="Cavidades activas del molde" model="paneles.cavidades">
                        <x-slot:summary><span x-text="cavidades ? cavidades + ' activas' : 'Todas'"></span></x-slot:summary>
                        <x-input-label for="cavidades_activas" value="¿Con cuántas cavidades trabajaste?" />
                        <x-text-input id="cavidades_activas" name="cavidades_activas" type="number"
                                      inputmode="numeric" min="1" max="64"
                                      class="mt-1.5 h-12 w-full sm:w-40" x-model="cavidades" />
                        <x-input-hint>Déjalo vacío si trabajaste con todas las cavidades.</x-input-hint>
                        <x-input-error :messages="$errors->get('cavidades_activas')" class="mt-2" />
                    </x-collapsible>

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
</x-app-layout>
