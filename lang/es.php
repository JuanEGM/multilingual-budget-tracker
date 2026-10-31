<?php
/**
 * Diccionario de Idioma Español
 * Gestor de Presupuesto Personal y Control de Gastos
 */

return [
    // Identidad de la aplicación
    'app_name'           => 'Gestor de Presupuesto Personal',
    'app_subtitle'       => 'Control de Gastos y Balance Financiero',
    'phase_tag'          => 'Fase 1+',

    // Navegación
    'nav_dashboard'      => 'Panel Principal',
    'nav_new_tx'         => 'Registrar Transacción',
    'nav_recurring'      => 'Suscripciones y Pagos Fijos',
    'nav_language'       => 'Idioma',
    'lang_en'            => 'Inglés',
    'lang_es'            => 'Español',

    // Tarjetas de Resumen (General)
    'card_total_income'  => 'Ingresos Totales',
    'card_total_expense' => 'Gastos Totales',
    'card_net_balance'   => 'Balance Neto',
    'card_balance_pos'   => 'Superávit',
    'card_balance_neg'   => 'Déficit',
    'card_balance_zero'  => 'Equilibrado',

    // Tarjetas y Textos de Pagos Fijos y Suscripciones
    'recurring_title'            => 'Pagos Fijos Mensuales y Suscripciones',
    'recurring_subtitle'         => 'Gestiona tus compromisos periódicos (Alquiler, Netflix, Spotify, Gimnasio, Salario fijo)',
    'card_fixed_income'          => 'Ingresos Fijos Mensuales',
    'card_fixed_expense'         => 'Gastos Fijos Mensuales',
    'card_projected_fixed'       => 'Balance Fijo Mensual Proyectado',
    'col_billing_day'            => 'Día de Cobro/Pago',
    'day_of_month_format'        => 'Día :day de cada mes',
    'col_status'                 => 'Estado',
    'status_active'              => 'Activo',
    'status_paused'              => 'Pausado',
    'btn_toggle_status'          => 'Alternar Activo / Pausado',
    'btn_apply_to_month'         => 'Registrar en este mes',
    'badge_applied_this_month'   => 'Registrado este mes',
    'badge_pending_this_month'   => 'Pendiente este mes',
    'btn_apply_all_pending'      => 'Registrar Todos los Pendientes de Este Mes',
    'btn_new_fixed'              => 'Nuevo Pago Fijo / Suscripción',
    'form_fixed_title'           => 'Añadir Compromiso Fijo / Suscripción',
    'form_fixed_subtitle'        => 'Registra un gasto periódico mensual automatizado o un ingreso regular',
    'form_field_day_of_month'    => 'Día de cobro/pago mensual (1 - 31)',
    'form_help_day_of_month'     => 'Día de cada mes en el que se ejecuta este pago.',

    // Tipos de Transacción e Insignias
    'type_income'        => 'Ingreso',
    'type_expense'       => 'Gasto',
    'badge_recurring'    => 'Recurrente',
    'badge_one_time'     => 'Puntual',

    // Columnas de la Tabla e Interfaz
    'table_title'        => 'Historial de Transacciones',
    'table_subtitle'     => 'Visión en tiempo real de tu flujo de efectivo',
    'col_id'             => '#',
    'col_type'           => 'Tipo',
    'col_concept'        => 'Concepto / Descripción',
    'col_category'       => 'Categoría',
    'col_date'           => 'Fecha',
    'col_amount'         => 'Monto',
    'col_actions'        => 'Acciones',
    'btn_delete'         => 'Eliminar',
    'delete_confirm'     => '¿Estás seguro de que deseas eliminar este registro? Esta acción no se puede deshacer.',

    // Estado Vacío
    'empty_title'        => 'No hay transacciones registradas todavía',
    'empty_subtitle'     => 'Comienza a tomar el control de tus finanzas personales registrando tu primer ingreso o gasto.',
    'btn_add_first'      => 'Registrar Primera Transacción',
    'empty_fixed_title'  => 'No hay pagos fijos ni suscripciones configuradas',
    'empty_fixed_desc'   => 'Añade suscripciones como Netflix, Spotify, alquiler, internet o tu sueldo fijo para proyectar y automatizar tus finanzas.',

    // Formulario: Nueva Transacción
    'form_title'                 => 'Registrar Transacción',
    'form_subtitle'              => 'Añade un nuevo ingreso o gasto a tu libro contable',
    'form_field_type'            => 'Tipo de Transacción',
    'form_select_type'           => '-- Seleccionar Tipo --',
    'form_field_concept'         => 'Concepto / Descripción',
    'form_placeholder_concept'   => 'Ej. Compra de supermercado, Salario mensual, Suscripción Netflix',
    'form_field_amount'          => 'Monto',
    'form_field_category'=> 'Categoría',
    'form_select_category'       => '-- Selecciona o escribe una categoría --',
    'form_field_date'            => 'Fecha de la Transacción',
    'form_save_as_recurring'     => 'Guardar también como pago fijo mensual recurrente / suscripción',
    'form_save_as_recurring_help'=> 'Si marcas esta opción, se creará también en tu panel de pagos fijos y suscripciones mensuales.',
    'btn_save'                   => 'Guardar Transacción',
    'btn_cancel'                 => 'Cancelar',
    'btn_back'                   => 'Volver al Panel',

    // Categorías por Defecto
    'cat_salary'         => 'Salario',
    'cat_freelance'      => 'Freelance / Honorarios',
    'cat_investments'    => 'Inversiones',
    'cat_food'           => 'Alimentación y Supermercado',
    'cat_housing'        => 'Vivienda y Alquiler',
    'cat_utilities'      => 'Servicios Públicos',
    'cat_transport'      => 'Transporte',
    'cat_entertainment'  => 'Entretenimiento y Ocio',
    'cat_health'         => 'Salud y Bienestar',
    'cat_education'      => 'Educación',
    'cat_other'          => 'Otros',

    // Mensajes Flash y Validaciones
    'msg_success_add'            => '¡Transacción registrada exitosamente!',
    'msg_success_delete'         => 'La transacción fue eliminada correctamente.',
    'msg_error_delete'           => 'No se pudo eliminar la transacción. Verifica el identificador.',
    'msg_error_not_found'        => 'No se encontró la transacción solicitada.',
    'msg_fixed_added'            => '¡Pago fijo / suscripción registrada con éxito!',
    'msg_fixed_applied'          => '¡La suscripción fue registrada en las transacciones de este mes!',
    'msg_fixed_status_updated'   => 'Estado de la suscripción actualizado.',
    'msg_fixed_deleted'          => 'Transacción fija eliminada con éxito.',
    'msg_all_fixed_applied'      => '¡Se registraron todas las suscripciones pendientes del mes actual!',
    'val_required_fields'        => 'Por favor completa todos los campos obligatorios.',
    'val_invalid_type'           => 'Tipo de transacción inválido. Debe ser Ingreso o Gasto.',
    'val_invalid_amount'         => 'El monto debe ser un valor numérico positivo mayor a 0.00.',
    'val_concept_len'            => 'La descripción del concepto no puede superar los 150 caracteres.',
    'val_category_len'           => 'El nombre de la categoría no puede superar los 50 caracteres.',
    'val_invalid_date'           => 'Ingresa una fecha válida en formato AAAA-MM-DD.',
    'val_invalid_day'            => 'El día del mes debe ser un número entre 1 y 31.',
    'val_db_error'               => 'Ocurrió un error en la base de datos al guardar. Intenta nuevamente.',

    // Pie de Página
    'footer_text'        => 'Gestor de Presupuesto Personal. PHP 8 Nativo, MySQL PDO y Bootstrap 5.',
    'all_rights_reserved'=> 'Todos los derechos reservados.',
];
