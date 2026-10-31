<?php
/**
 * Diccionario de Idioma Español
 * Gestor de Presupuesto Personal y Control de Gastos
 */

return [
    // Identidad de la aplicación
    'app_name'           => 'Gestor de Presupuesto Personal',
    'app_subtitle'       => 'Control de Gastos y Balance Financiero',
    'phase_tag'          => 'Fase 1',

    // Navegación
    'nav_dashboard'      => 'Panel Principal',
    'nav_new_tx'         => 'Registrar Transacción',
    'nav_language'       => 'Idioma',
    'lang_en'            => 'Inglés',
    'lang_es'            => 'Español',

    // Tarjetas de Resumen
    'card_total_income'  => 'Ingresos Totales',
    'card_total_expense' => 'Gastos Totales',
    'card_net_balance'   => 'Balance Neto',
    'card_balance_pos'   => 'Superávit',
    'card_balance_neg'   => 'Déficit',
    'card_balance_zero'  => 'Equilibrado',

    // Tipos de Transacción e Insignias
    'type_income'        => 'Ingreso',
    'type_expense'       => 'Gasto',

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
    'delete_confirm'     => '¿Estás seguro de que deseas eliminar esta transacción? Esta acción no se puede deshacer.',

    // Estado Vacío
    'empty_title'        => 'No hay transacciones registradas todavía',
    'empty_subtitle'     => 'Comienza a tomar el control de tus finanzas personales registrando tu primer ingreso o gasto.',
    'btn_add_first'      => 'Registrar Primera Transacción',

    // Formulario: Nueva Transacción
    'form_title'         => 'Registrar Transacción',
    'form_subtitle'      => 'Añade un nuevo ingreso o gasto a tu libro contable',
    'form_field_type'    => 'Tipo de Transacción',
    'form_select_type'   => '-- Seleccionar Tipo --',
    'form_field_concept' => 'Concepto / Descripción',
    'form_placeholder_concept' => 'Ej. Compra de supermercado, Salario mensual, Suscripción Netflix',
    'form_field_amount'  => 'Monto',
    'form_field_category'=> 'Categoría',
    'form_select_category' => '-- Selecciona o escribe una categoría --',
    'form_field_date'    => 'Fecha de la Transacción',
    'btn_save'           => 'Guardar Transacción',
    'btn_cancel'         => 'Cancelar',
    'btn_back'           => 'Volver al Panel',

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
    'msg_success_add'    => '¡Transacción registrada exitosamente!',
    'msg_success_delete' => 'La transacción fue eliminada correctamente.',
    'msg_error_delete'   => 'No se pudo eliminar la transacción. Verifica el identificador.',
    'msg_error_not_found'=> 'No se encontró la transacción solicitada.',
    'val_required_fields'=> 'Por favor completa todos los campos obligatorios.',
    'val_invalid_type'   => 'Tipo de transacción inválido. Debe ser Ingreso o Gasto.',
    'val_invalid_amount' => 'El monto debe ser un valor numérico positivo mayor a 0.00.',
    'val_concept_len'    => 'La descripción del concepto no puede superar los 150 caracteres.',
    'val_category_len'   => 'El nombre de la categoría no puede superar los 50 caracteres.',
    'val_invalid_date'   => 'Ingresa una fecha válida en formato AAAA-MM-DD.',
    'val_db_error'       => 'Ocurrió un error en la base de datos al guardar. Intenta nuevamente.',

    // Pie de Página
    'footer_text'        => 'Gestor de Presupuesto Personal - Fase 1. PHP 8 Nativo, MySQL PDO y Bootstrap 5.',
    'all_rights_reserved'=> 'Todos los derechos reservados.',
];
