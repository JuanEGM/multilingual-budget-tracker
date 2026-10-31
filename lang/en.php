<?php
/**
 * English Language Dictionary (Default)
 * Gestor de Presupuesto Personal y Control de Gastos
 */

return [
    // Application branding
    'app_name'           => 'Personal Budget Manager',
    'app_subtitle'       => 'Expense Tracker & Financial Balance',
    'phase_tag'          => 'Phase 1',

    // Navigation
    'nav_dashboard'      => 'Dashboard',
    'nav_new_tx'         => 'Add Transaction',
    'nav_language'       => 'Language',
    'lang_en'            => 'English',
    'lang_es'            => 'Spanish',

    // Summary Cards
    'card_total_income'  => 'Total Income',
    'card_total_expense' => 'Total Expenses',
    'card_net_balance'   => 'Net Balance',
    'card_balance_pos'   => 'Surplus',
    'card_balance_neg'   => 'Deficit',
    'card_balance_zero'  => 'Balanced',

    // Transaction Types & Badges
    'type_income'        => 'Income',
    'type_expense'       => 'Expense',

    // Table Columns & UI
    'table_title'        => 'Recent Transactions',
    'table_subtitle'     => 'Real-time overview of your cash flow',
    'col_id'             => '#',
    'col_type'           => 'Type',
    'col_concept'        => 'Concept / Description',
    'col_category'       => 'Category',
    'col_date'           => 'Date',
    'col_amount'         => 'Amount',
    'col_actions'        => 'Actions',
    'btn_delete'         => 'Delete',
    'delete_confirm'     => 'Are you sure you want to delete this transaction? This action cannot be undone.',

    // Empty State
    'empty_title'        => 'No transactions recorded yet',
    'empty_subtitle'     => 'Start taking control of your personal finances by registering your first income or expense.',
    'btn_add_first'      => 'Add Your First Transaction',

    // Form: New Transaction
    'form_title'         => 'Register Transaction',
    'form_subtitle'      => 'Record a new income or expense in your ledger',
    'form_field_type'    => 'Transaction Type',
    'form_select_type'   => '-- Select Type --',
    'form_field_concept' => 'Concept / Description',
    'form_placeholder_concept' => 'e.g., Grocery shopping, Monthly salary, Netflix subscription',
    'form_field_amount'  => 'Amount',
    'form_field_category'=> 'Category',
    'form_select_category' => '-- Select or type a category --',
    'form_field_date'    => 'Transaction Date',
    'btn_save'           => 'Save Transaction',
    'btn_cancel'         => 'Cancel',
    'btn_back'           => 'Back to Dashboard',

    // Default Categories
    'cat_salary'         => 'Salary',
    'cat_freelance'      => 'Freelance',
    'cat_investments'    => 'Investments',
    'cat_food'           => 'Food & Groceries',
    'cat_housing'        => 'Housing & Rent',
    'cat_utilities'      => 'Utilities & Services',
    'cat_transport'      => 'Transportation',
    'cat_entertainment'  => 'Entertainment & Leisure',
    'cat_health'         => 'Health & Wellness',
    'cat_education'      => 'Education',
    'cat_other'          => 'Other',

    // Flash & Validation Messages
    'msg_success_add'    => 'Transaction recorded successfully!',
    'msg_success_delete' => 'Transaction was successfully deleted.',
    'msg_error_delete'   => 'Could not delete transaction. Please verify the ID.',
    'msg_error_not_found'=> 'The requested transaction was not found.',
    'val_required_fields'=> 'Please fill in all required fields.',
    'val_invalid_type'   => 'Invalid transaction type selected. Must be Income or Expense.',
    'val_invalid_amount' => 'Amount must be a positive numeric value greater than 0.00.',
    'val_concept_len'    => 'Concept description cannot exceed 150 characters.',
    'val_category_len'   => 'Category name cannot exceed 50 characters.',
    'val_invalid_date'   => 'Please provide a valid date in YYYY-MM-DD format.',
    'val_db_error'       => 'A database error occurred while saving. Please try again.',

    // Footer
    'footer_text'        => 'Personal Budget Manager - Phase 1. Native PHP 8, MySQL PDO & Bootstrap 5.',
    'all_rights_reserved'=> 'All rights reserved.',
];
