{{--
    Solicitud PÚBLICA de visita/revisión industrial (QR, sin login): para
    lavadoras, llenadoras y plantas de osmosis EN EL CLIENTE. SIEMPRE es una
    visita técnica (diagnóstico + cotización): el cliente no elige el tipo de
    trabajo — ver AgendaTrabajo::TIPO_PUBLICO. Opcionalmente indica el servicio
    del tarifario, deja sus datos y una fecha preferida opcional. Entra a la
    Agenda de terreno como 'solicitado'; el staff llama y coordina.
--}}
<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-xl font-bold tracking-tight text-neutral-900">Visita / revisión industrial</h1>
        <p class="mt-1 text-sm text-neutral-500">
            Sucursal <span class="font-medium text-neutral-700">{{ $sucursal->nombre }}</span>
        </p>
        <p class="mt-3 text-sm text-neutral-500">
            El técnico va a tu planta. Deja tus datos y te llamamos para coordinar el día.
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            Revisa los datos: hay {{ $errors->count() }} campo(s) con problemas más abajo.
        </div>
    @endif

    {{-- EL CARTEL DE DISPONIBILIDAD (pedido del dueño 13-08-2026: «cuando el cliente
         ingrese una fecha que diga si está disponible u ocupada, un cartel de advertencia
         que no se puede ese día o varios días»).

         El chequeo YA EXISTÍA en el servidor, pero recién al ENVIAR: el cliente llenaba
         nombre, RUT, teléfono, correo, dirección y ciudad, apretaba Enviar y ahí se
         enteraba de que ese día no había. Esto pregunta lo MISMO —el mismo
         `AgendaTrabajo::disponibilidad()`, que es el mismo `conflictos()` de la agenda
         interna— en cuanto elige la fecha.

         SI LA CONSULTA FALLA NO SE BLOQUEA NADA: se avisa y se deja enviar. El veredicto
         que manda sigue siendo el del servidor al recibir el formulario; esto es un aviso
         temprano, no una segunda regla. --}}
    <form method="POST" action="{{ route('visita-industrial.store') }}" class="space-y-5 pb-[calc(6rem_+_env(safe-area-inset-bottom))] sm:pb-0"
          x-data="{
              servicioId: @js(old('servicio_terreno_id', '')),
              fecha: @js(old('fecha_preferida', '')),
              url: @js(route('visita-industrial.disponibilidad')),
              estado: null, tramo: null, cerrado: null, proximo: null, proximoIso: null, dias: 0,

              async revisar() {
                  if (! this.fecha) { this.estado = null; return; }
                  this.estado = 'consultando';
                  try {
                      const r = await fetch(`${this.url}?fecha=${encodeURIComponent(this.fecha)}`,
                                           { headers: { Accept: 'application/json' } });
                      if (! r.ok) { this.estado = 'error'; return; }
                      const d = await r.json();
                      this.dias = d.dias;
                      this.tramo = d.etiqueta_tramo;
                      this.cerrado = d.etiqueta_cerrado;
                      this.proximo = d.etiqueta_proximo;
                      this.proximoIso = d.proximo_libre;
                      // El servidor manda el estado; el cartel no lo deduce. Si mañana
                      // aparece uno nuevo (media jornada), acá no hay nada que adivinar.
                      this.estado = d.estado;
                  } catch (e) {
                      this.estado = 'error';
                  }
              },
              usarProximo() {
                  if (! this.proximoIso) return;
                  this.fecha = this.proximoIso;
                  this.revisar();
              },
          }"
          x-init="revisar()">
        @csrf
        <input type="hidden" name="sucursal_id" value="{{ $sucursal->id }}">
        {{-- Honeypot anti-bot --}}
        <div class="hidden" aria-hidden="true">
            <label>Sitio web <input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
        </div>

        {{-- Qué necesitas --}}
        <x-seccion titulo="¿Qué necesitas?">
            {{-- El cliente YA NO elige el tipo de trabajo (pedido del técnico
                 industrial, 13-08): no puede saber si lo suyo es mantención,
                 reparación o instalación, y elegir mal desviaba la visita. Lo
                 público es siempre una visita técnica; el trabajo que salga de
                 ella lo agenda después el vendedor o el jefe de ventas hablando
                 con el cliente. Acá queda DICHO qué se está pidiendo, para que
                 nadie crea que se le olvidó un campo. --}}
            <div class="rounded-xl border border-brand-200 bg-brand-50 p-3 text-sm text-neutral-700">
                <p class="font-semibold text-brand-700">Visita técnica</p>
                <p class="mt-1">
                    El técnico va a tu planta, revisa el equipo y te cotiza lo que haya que hacer.
                    Si después hay que hacer una mantención, una reparación o una instalación,
                    lo coordinamos contigo.
                </p>
            </div>
            <div>
                <x-input-label for="servicio_terreno_id" value="Servicio (si ya sabes cuál)" />
                <x-select id="servicio_terreno_id" name="servicio_terreno_id" class="mt-1.5" x-model="servicioId">
                    <option value="">— No estoy seguro / que me orienten —</option>
                    {{-- Solo el NOMBRE del servicio: el valor en UF es interno y no
                         se le muestra al cliente (lo cotiza el técnico en la visita). --}}
                    @foreach ($servicios as $s)
                        <option value="{{ $s->id }}" @selected((string) old('servicio_terreno_id') === (string) $s->id)>
                            {{ $s->nombre }}
                        </option>
                    @endforeach
                </x-select>
                <x-input-error :messages="$errors->get('servicio_terreno_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="descripcion">Cuéntanos qué pasa <span class="text-red-500">*</span></x-input-label>
                <x-textarea id="descripcion" name="descripcion" rows="3" class="mt-1.5" required
                    placeholder="Ej. La planta de osmosis 1T pierde presión; la llenadora traba la cadena.">{{ old('descripcion') }}</x-textarea>
                <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="fecha_preferida" value="¿Cuándo te acomoda? (opcional)" />
                <x-text-input id="fecha_preferida" name="fecha_preferida" type="date" class="mt-1.5 w-full"
                    min="{{ \App\Support\FechaNegocio::hoy() }}" x-model="fecha" @change="revisar()" />
                <x-input-hint>Es una referencia: el día definitivo se coordina contigo.</x-input-hint>

                {{-- Cuatro estados y ninguno bloquea el envío por su cuenta. El «ocupado»
                     dice de frente que con ese día la solicitud no se va a poder enviar,
                     porque el servidor la rechaza: avisarlo acá y no descubrirlo al final
                     es todo el punto de este cartel. --}}
                <template x-if="estado === 'consultando'">
                    <p class="mt-2 text-xs text-neutral-500">Revisando la agenda…</p>
                </template>

                <template x-if="estado === 'libre'">
                    <p class="mt-2 flex items-start gap-1.5 text-sm font-medium text-brand-700">
                        <span aria-hidden="true">✓</span>
                        <span>Ese día hay disponibilidad.</span>
                    </p>
                </template>

                {{-- «Cerrado» y «ocupado» comparten la caja pero NO el texto: al cliente le
                     sirve saber si el día no se atiende (y entonces ni insiste) o si está
                     tomado (y entonces prueba otro cercano). Del motivo de fondo no se dice
                     nada — decisión del dueño: «no es tan importante que la gente sepa que
                     está de vacaciones, simplemente no está disponible». --}}
                <template x-if="estado === 'cerrado' || estado === 'ocupado'">
                    <div class="mt-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                        <p class="font-semibold">
                            <span aria-hidden="true">⚠</span>
                            <span x-text="estado === 'cerrado'
                                ? cerrado
                                : (dias > 1
                                    ? `La agenda está ocupada ${tramo}.`
                                    : `Ese día la agenda está ocupada (${tramo}).`)"></span>
                        </p>
                        <p class="mt-1" x-show="proximo" x-cloak>
                            El día más cercano disponible es el <span class="font-medium" x-text="proximo"></span>.
                        </p>
                        <p class="mt-1 text-amber-800">
                            Elige otra fecha o deja el campo vacío y coordinamos por teléfono.
                        </p>
                        {{-- Las clases son las del botón «Volver al inicio» de esta misma
                             vista, a propósito: el CSS va commiteado y construirlo de nuevo
                             solo para dos tonos de ámbar nuevos no vale el riesgo. --}}
                        <button type="button" @click="usarProximo()" x-show="proximoIso" x-cloak
                                class="mt-2 min-h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">
                            Usar el día disponible
                        </button>
                    </div>
                </template>

                {{-- Si la consulta falla, se dice y se sigue. Un formulario público que no
                     deja pedir una visita porque no pudo leer la agenda es peor que uno que
                     acepta una fecha que después hay que mover por teléfono. --}}
                <template x-if="estado === 'error'">
                    <p class="mt-2 text-xs text-neutral-500">
                        No pudimos revisar la agenda en este momento. Puedes enviar la solicitud igual: coordinamos contigo.
                    </p>
                </template>

                <x-input-error :messages="$errors->get('fecha_preferida')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="disponibilidad" value="¿Cuándo puedes y cuándo no? (opcional)" />
                <x-textarea id="disponibilidad" name="disponibilidad" rows="3" class="mt-1.5" maxlength="1000"
                    placeholder="Ej. Fines de semana no; ir después de las 15 h; el taller cierra a las 18 h; avisar antes de llegar.">{{ old('disponibilidad') }}</x-textarea>
                <x-input-hint>Cuéntanos tus horarios o restricciones para que coordinemos la visita a tu medida.</x-input-hint>
                <x-input-error :messages="$errors->get('disponibilidad')" class="mt-2" />
            </div>
        </x-seccion>

        {{-- Tus datos --}}
        <x-seccion titulo="Tus datos">
            <div>
                <x-input-label for="cliente_nombre">Nombre / empresa <span class="text-red-500">*</span></x-input-label>
                <x-text-input id="cliente_nombre" name="cliente_nombre" type="text" class="mt-1.5 w-full" required
                    maxlength="191" :value="old('cliente_nombre')" />
                <x-input-error :messages="$errors->get('cliente_nombre')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="cliente_rut">RUT <span class="text-red-500">*</span></x-input-label>
                <x-text-input id="cliente_rut" name="cliente_rut" type="text" class="mt-1.5 w-full" required
                    maxlength="20" placeholder="Ej. 76.123.456-7" :value="old('cliente_rut')" />
                <x-input-error :messages="$errors->get('cliente_rut')" class="mt-2" />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="cliente_telefono">Teléfono <span class="text-red-500">*</span></x-input-label>
                    <x-text-input id="cliente_telefono" name="cliente_telefono" type="tel" class="mt-1.5 w-full" required
                        maxlength="30" placeholder="+56 9 1234 5678" :value="old('cliente_telefono')" />
                    <x-input-hint>Te llamamos a este número para coordinar.</x-input-hint>
                    <x-input-error :messages="$errors->get('cliente_telefono')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="cliente_email">Correo <span class="text-red-500">*</span></x-input-label>
                    <x-text-input id="cliente_email" name="cliente_email" type="email" class="mt-1.5 w-full" required
                        maxlength="191" :value="old('cliente_email')" />
                    <x-input-error :messages="$errors->get('cliente_email')" class="mt-2" />
                </div>
            </div>
            <div>
                <x-input-label for="direccion">Dirección de la planta <span class="text-red-500">*</span></x-input-label>
                <x-text-input id="direccion" name="direccion" type="text" class="mt-1.5 w-full" required
                    maxlength="191" placeholder="Donde se hará la visita" :value="old('direccion')" />
                <x-input-error :messages="$errors->get('direccion')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="ciudad">Ciudad <span class="text-red-500">*</span></x-input-label>
                <x-text-input id="ciudad" name="ciudad" type="text" class="mt-1.5 w-full" required
                    maxlength="191" :value="old('ciudad')" />
                <x-input-error :messages="$errors->get('ciudad')" class="mt-2" />
            </div>
        </x-seccion>

        <x-barra-envio-movil>Enviar solicitud</x-barra-envio-movil>
        <p class="text-center text-xs text-neutral-400">Te contactaremos para coordinar el día y la hora de la visita.</p>

        {{-- Volver a la pantalla principal (elegir por unidad / por cantidad).
             Secundario para no competir con el envío. --}}
        @if (! empty($urlInicio))
            <a href="{{ $urlInicio }}"
               class="block w-full rounded-xl border border-neutral-300 bg-white px-5 py-3 text-center text-sm font-medium text-neutral-700 shadow-sm transition hover:bg-neutral-50">
                Volver al inicio
            </a>
        @endif
    </form>
</x-guest-layout>
