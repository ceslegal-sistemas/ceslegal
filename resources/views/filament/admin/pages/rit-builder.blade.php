<x-filament-panels::page>

    {{-- Banner informativo --}}
    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-700 mb-6">
        <div class="flex items-start gap-3">
            <x-heroicon-o-document-text class="w-8 h-8 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
            <div>
                <p class="font-semibold text-blue-900 dark:text-blue-100 text-base">Constructor de Reglamento Interno de Trabajo</p>
                <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                    Responda las siguientes preguntas sobre su empresa. Con esta información, la IA redactará un Reglamento Interno completo
                    con cumplimiento del Art. 105 CST y la Ley 2365/2024 (acoso sexual). Al finalizar podrá descargar el documento en Word.
                </p>
                @if($this->yaExisteRIT())
                    <p class="text-sm font-semibold text-amber-700 dark:text-amber-300 mt-2">
                        Su empresa ya tiene un Reglamento Interno activo. Completar este formulario lo reemplazará.
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Error --}}
    @if($error)
        <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-xl border border-red-200 dark:border-red-700 mb-6">
            <p class="text-sm text-red-800 dark:text-red-200">{{ $error }}</p>
        </div>
    @endif

    {{-- Formulario en secciones --}}
    <div class="space-y-4">

        {{-- Sección 1: Datos generales --}}
        <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900" open>
            <summary class="flex items-center justify-between p-4 cursor-pointer font-semibold text-gray-900 dark:text-gray-100 list-none">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-building-office-2 class="w-5 h-5 text-primary-500" />
                    1. Datos Generales de la Empresa
                </span>
                <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-500 group-open:rotate-180 transition-transform" />
            </summary>
            <div class="px-4 pb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Razón Social completa <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="respuestas.razon_social_completa" disabled
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           placeholder="Ej: EMPRESA ABC S.A.S" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">NIT <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="respuestas.nit_empresa" disabled
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           placeholder="900123456-7" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Domicilio principal</label>
                    <input type="text" wire:model="respuestas.domicilio_principal" disabled
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           placeholder="Calle 123 # 45-67, Bogotá D.C." />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Actividad económica principal <span class="text-red-500">*</span></label>
                    {{-- <select wire:model="respuestas.actividad_economica" disabled
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        @foreach($actividadesEconomicas as $actividad)
                            <option value="{{ $actividad }}">{{ $actividad }}</option>
                        @endforeach
                    </select> --}}
                    <input type="text" wire:model="respuestas.actividad_economica"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           placeholder="Ej: Comercio al por menor de prendas de vestir (CIIU 4771)" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número aproximado de trabajadores <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="respuestas.numero_trabajadores"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           placeholder="Ej: 15" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Tiene sucursales?</label>
                    <select wire:model="respuestas.tiene_sucursales"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="No">No</option>
                        <option value="Sí">Sí</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Si tiene sucursales, indique ciudades y número de trabajadores en cada una</label>
                    <textarea wire:model="respuestas.sucursales_detalle" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Medellín: 3 trabajadores, Cali: 2 trabajadores"></textarea>
                </div>
            </div>
        </details>

        {{-- Sección 2: Estructura organizacional --}}
        <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <summary class="flex items-center justify-between p-4 cursor-pointer font-semibold text-gray-900 dark:text-gray-100 list-none">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-users class="w-5 h-5 text-primary-500" />
                    2. Estructura Organizacional
                </span>
                <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-500 group-open:rotate-180 transition-transform" />
            </summary>
            <div class="px-4 pb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cargos/jerarquía de la empresa (de mayor a menor)</label>
                    <textarea wire:model="respuestas.jerarquia_cargos" rows="3"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Gerente General → Jefe de Área → Coordinador → Operario"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Qué cargos tienen facultad para imponer sanciones?</label>
                    <input type="text" wire:model="respuestas.cargos_con_sancion"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           placeholder="Ej: Gerente General, Directores de área" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Tiene manual de funciones?</label>
                    <select wire:model="respuestas.tiene_manual_funciones"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="Sí">Sí</option>
                        <option value="No">No</option>
                        <option value="En construcción">En construcción</option>
                    </select>
                </div>
            </div>
        </details>

        {{-- Sección 3: Contratos --}}
        <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <summary class="flex items-center justify-between p-4 cursor-pointer font-semibold text-gray-900 dark:text-gray-100 list-none">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-document-duplicate class="w-5 h-5 text-primary-500" />
                    3. Tipos de Contratos
                </span>
                <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-500 group-open:rotate-180 transition-transform" />
            </summary>
            <div class="px-4 pb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Qué tipos de contrato maneja la empresa?</label>
                    <textarea wire:model="respuestas.tipos_contrato_usados" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Término indefinido, término fijo a 1 año, obra o labor"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Tiene trabajadores en misión o temporales?</label>
                    <select wire:model="respuestas.tiene_trabajadores_mision"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="No">No</option>
                        <option value="Sí">Sí</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Tiene o ha tenido contratos de aprendizaje (SENA)?</label>
                    <select wire:model="respuestas.tiene_aprendices"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="No">No</option>
                        <option value="Sí">Sí</option>
                    </select>
                </div>
            </div>
        </details>

        {{-- Sección 4: Jornada laboral --}}
        <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <summary class="flex items-center justify-between p-4 cursor-pointer font-semibold text-gray-900 dark:text-gray-100 list-none">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-clock class="w-5 h-5 text-primary-500" />
                    4. Jornada Laboral
                </span>
                <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-500 group-open:rotate-180 transition-transform" />
            </summary>
            <div class="px-4 pb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hora de entrada <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="respuestas.horario_entrada"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           placeholder="Ej: 8:00 a.m." />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hora de salida <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="respuestas.horario_salida"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           placeholder="Ej: 6:00 p.m." />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Trabaja los sábados?</label>
                    <select wire:model="respuestas.trabaja_sabados"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="No">No</option>
                        <option value="Sí, mañana">Sí, mañana</option>
                        <option value="Sí, día completo">Sí, día completo</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Trabaja domingos o festivos?</label>
                    <select wire:model="respuestas.trabaja_dominicales"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="No">No</option>
                        <option value="Ocasionalmente">Ocasionalmente</option>
                        <option value="Sí, regularmente">Sí, regularmente</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Tiene turnos rotativos o nocturnos?</label>
                    <select wire:model="respuestas.tiene_turnos"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="No">No</option>
                        <option value="Sí">Sí</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Cómo controla la asistencia?</label>
                    <input type="text" wire:model="respuestas.control_asistencia"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           placeholder="Ej: Reloj biométrico, planilla manual, app" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Política de horas extras</label>
                    <textarea wire:model="respuestas.politica_horas_extras" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Se pagan con el recargo legal. Requieren autorización previa del jefe inmediato"></textarea>
                </div>
            </div>
        </details>

        {{-- Sección 5: Salario y beneficios --}}
        <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <summary class="flex items-center justify-between p-4 cursor-pointer font-semibold text-gray-900 dark:text-gray-100 list-none">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-banknotes class="w-5 h-5 text-primary-500" />
                    5. Salario y Beneficios
                </span>
                <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-500 group-open:rotate-180 transition-transform" />
            </summary>
            <div class="px-4 pb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Forma de pago del salario <span class="text-red-500">*</span></label>
                    <select wire:model="respuestas.forma_pago"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="Transferencia bancaria">Transferencia bancaria</option>
                        <option value="Cheque">Cheque</option>
                        <option value="Efectivo">Efectivo</option>
                        <option value="Mixto (transferencia y efectivo)">Mixto</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Periodicidad de pago</label>
                    <select wire:model="respuestas.periodicidad_pago"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="Mensual">Mensual (último día hábil)</option>
                        <option value="Quincenal">Quincenal (15 y último día)</option>
                        <option value="Semanal">Semanal</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Maneja comisiones o bonos?</label>
                    <select wire:model="respuestas.maneja_comisiones"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="No">No</option>
                        <option value="Sí, comisiones de ventas">Sí, comisiones de ventas</option>
                        <option value="Sí, bonos por desempeño">Sí, bonos por desempeño</option>
                        <option value="Sí, comisiones y bonos">Sí, comisiones y bonos</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Beneficios extralegales que otorga (si aplica)</label>
                    <textarea wire:model="respuestas.beneficios_extralegales" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Auxilio de alimentación $150.000/mes, seguro de vida, día de cumpleaños libre"></textarea>
                </div>
            </div>
        </details>

        {{-- Sección 6: Permisos y licencias --}}
        <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <summary class="flex items-center justify-between p-4 cursor-pointer font-semibold text-gray-900 dark:text-gray-100 list-none">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-calendar-days class="w-5 h-5 text-primary-500" />
                    6. Permisos y Licencias
                </span>
                <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-500 group-open:rotate-180 transition-transform" />
            </summary>
            <div class="px-4 pb-4 grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Cómo maneja los permisos personales?</label>
                    <textarea wire:model="respuestas.politica_permisos" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Solicitud escrita con 24 horas de anticipación al jefe inmediato, máximo 2 días al mes pagos"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Licencias especiales que otorga (adicionales a las legales)</label>
                    <textarea wire:model="respuestas.licencias_especiales" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Licencia por calamidad doméstica 3 días, día de matrimonio remunerado"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Cómo maneja las incapacidades médicas?</label>
                    <textarea wire:model="respuestas.politica_incapacidades" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Presentación de incapacidad dentro de las 48 horas siguientes. Primeros 2 días a cargo del empleador."></textarea>
                </div>
            </div>
        </details>

        {{-- Sección 7: Régimen disciplinario --}}
        <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <summary class="flex items-center justify-between p-4 cursor-pointer font-semibold text-gray-900 dark:text-gray-100 list-none">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-scale class="w-5 h-5 text-primary-500" />
                    7. Régimen Disciplinario
                </span>
                <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-500 group-open:rotate-180 transition-transform" />
            </summary>
            <div class="px-4 pb-4 grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ejemplos de faltas leves en su empresa</label>
                    <textarea wire:model="respuestas.ejemplos_faltas_leves" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Llegar tarde, no registrar asistencia, no usar el uniforme"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ejemplos de faltas graves en su empresa</label>
                    <textarea wire:model="respuestas.ejemplos_faltas_graves" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Agredir verbalmente a un compañero, incumplir normas de seguridad, ausentarse sin justificación"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ejemplos de faltas muy graves en su empresa</label>
                    <textarea wire:model="respuestas.ejemplos_faltas_muy_graves" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Hurto, agresión física, acoso sexual, divulgación de secretos empresariales"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sanciones que contempla aplicar</label>
                    <textarea wire:model="respuestas.sanciones_contempladas" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Llamado de atención verbal, llamado de atención escrito, suspensión 1-5 días, terminación con justa causa"></textarea>
                </div>
            </div>
        </details>

        {{-- Sección 8: SST --}}
        <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <summary class="flex items-center justify-between p-4 cursor-pointer font-semibold text-gray-900 dark:text-gray-100 list-none">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-shield-check class="w-5 h-5 text-primary-500" />
                    8. Seguridad y Salud en el Trabajo (SST)
                </span>
                <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-500 group-open:rotate-180 transition-transform" />
            </summary>
            <div class="px-4 pb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Tiene implementado el SG-SST?</label>
                    <select wire:model="respuestas.tiene_sg_sst"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="Sí, implementado">Sí, implementado</option>
                        <option value="En proceso de implementación">En proceso de implementación</option>
                        <option value="No">No</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">EPP requeridos para el cargo</label>
                    <input type="text" wire:model="respuestas.epp_requeridos"
                           class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                           placeholder="Ej: Casco, guantes, botas de seguridad / No aplica (oficina)" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Principales riesgos en su operación</label>
                    <textarea wire:model="respuestas.riesgos_principales" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Riesgo ergonómico (trabajo en pantallas), riesgo público (atención al cliente)"></textarea>
                </div>
            </div>
        </details>

        {{-- Sección 9: Conducta y equipos --}}
        <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <summary class="flex items-center justify-between p-4 cursor-pointer font-semibold text-gray-900 dark:text-gray-100 list-none">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-device-phone-mobile class="w-5 h-5 text-primary-500" />
                    9. Conducta, Uniformes y Equipos
                </span>
                <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-500 group-open:rotate-180 transition-transform" />
            </summary>
            <div class="px-4 pb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Política de uso de celular en horario laboral</label>
                    <select wire:model="respuestas.politica_celular"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="Libre uso">Libre uso</option>
                        <option value="Solo en descansos">Solo en descansos</option>
                        <option value="Prohibido salvo emergencias">Prohibido salvo emergencias</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Usa uniforme o ropa de trabajo?</label>
                    <select wire:model="respuestas.usa_uniforme"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="No">No</option>
                        <option value="Sí, uniforme completo">Sí, uniforme completo</option>
                        <option value="Sí, dotación básica">Sí, dotación básica</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Tiene código de ética o conducta?</label>
                    <select wire:model="respuestas.tiene_codigo_etica"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="Sí">Sí</option>
                        <option value="No">No</option>
                        <option value="En construcción">En construcción</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Tiene política de confidencialidad?</label>
                    <select wire:model="respuestas.politica_confidencialidad"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Seleccione...</option>
                        <option value="Sí, por contrato">Sí, por contrato</option>
                        <option value="Sí, pero solo verbal">Sí, pero solo verbal</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>
        </details>

        {{-- Sección 10: Contexto y riesgos --}}
        <details class="group rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <summary class="flex items-center justify-between p-4 cursor-pointer font-semibold text-gray-900 dark:text-gray-100 list-none">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-light-bulb class="w-5 h-5 text-primary-500" />
                    10. Contexto y Riesgos Operacionales
                </span>
                <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-500 group-open:rotate-180 transition-transform" />
            </summary>
            <div class="px-4 pb-4 grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Ha tenido problemas disciplinarios previos que quiera cubrir específicamente?</label>
                    <textarea wire:model="respuestas.problemas_disciplinarios_previos" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Problemas de puntualidad reiterados, conflictos entre compañeros de trabajo"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Qué conductas quiere prevenir principalmente con este RIT?</label>
                    <textarea wire:model="respuestas.que_quiere_prevenir" rows="2"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Uso indebido de información confidencial, conflictos interpersonales, impuntualidad crónica"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Otras políticas o aspectos relevantes que deba incluir el RIT</label>
                    <textarea wire:model="respuestas.otras_politicas" rows="3"
                              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                              placeholder="Ej: Política de teletrabajo, uso de vehículos de empresa, manejo de caja menor..."></textarea>
                </div>
            </div>
        </details>

    </div>

    {{-- Botón de construcción --}}
    <div class="mt-6 flex flex-col items-center gap-4">
        <button
            wire:click="construir"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-primary-600 text-white font-semibold text-base hover:bg-primary-500 focus:ring-2 focus:ring-primary-500 transition-colors shadow-md disabled:opacity-60 cursor-pointer disabled:cursor-not-allowed"
        >
            <span wire:loading.remove wire:target="construir">
                <x-heroicon-o-cpu-chip class="w-5 h-5 inline" />
                Construir Reglamento Interno con IA
            </span>
            <span wire:loading wire:target="construir" class="flex items-center gap-2">
                <svg class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Generando su Reglamento Interno... (puede tardar hasta 60 segundos)
            </span>
        </button>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            La IA redactará un Reglamento Interno completo basado en sus respuestas. Este proceso puede tardar hasta 60 segundos.
        </p>
    </div>

    {{-- Panel de resultado --}}
    @if($textoGenerado || $docxPath)
        <div class="mt-8 rounded-xl border border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900/20 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-green-900 dark:text-green-100 flex items-center gap-2">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-600" />
                    ¡Reglamento Interno generado exitosamente!
                </h3>
                @php $descargarUrl = $this->getDescargarUrl(); @endphp
                @if($descargarUrl)
                    <a href="{{ $descargarUrl }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-600 text-white font-semibold text-sm hover:bg-green-500 transition-colors shadow-sm">
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                        Descargar .docx
                    </a>
                @endif
            </div>

            @if($textoGenerado)
                <div class="mt-4">
                    <p class="text-sm font-medium text-green-800 dark:text-green-200 mb-2">Vista previa del documento generado:</p>
                    <div class="bg-white dark:bg-gray-900 rounded-lg border border-green-200 dark:border-green-700 p-4 max-h-96 overflow-y-auto">
                        <pre class="text-xs text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-mono">{{ $textoGenerado }}</pre>
                    </div>
                </div>
            @endif

            <div class="pt-2 border-t border-green-200 dark:border-green-700">
                <p class="text-xs text-green-700 dark:text-green-300">
                    <strong>Importante:</strong> Este documento fue generado con IA y debe ser revisado por un abogado laboral antes de ser presentado ante el Ministerio del Trabajo. La plataforma no garantiza su aprobación.
                </p>
            </div>
        </div>
    @endif

</x-filament-panels::page>
