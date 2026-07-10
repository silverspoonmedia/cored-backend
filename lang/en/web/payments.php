<?php

return [
    'gateways' => [
        'title' => 'Payment gateways',
        'list_title' => 'Payment gateway list',
        'datatable' => [
            'empty' => 'No payment gateways configured.',
            'zero_records' => 'No payment gateways match your search.',
            'columns' => [
                'name' => 'Name',
                'display_name' => 'Display name',
                'is_active' => 'Active',
                'priority' => 'Priority',
            ],
        ],
    ],

    'invoices' => [
        'title' => 'Invoices',
        'list_title' => 'Invoice list',
        'show_title' => 'Invoice detail',
        'show_card_title' => 'Invoice :number',
        'back_to_list' => 'Back to invoices',
        'datatable' => [
            'empty' => 'No invoices found.',
            'zero_records' => 'No invoices match your search.',
            'columns' => [
                'invoice_number' => 'Invoice #',
                'status' => 'Status',
                'total' => 'Total',
                'created_at' => 'Created',
                'actions' => 'Actions',
            ],
        ],
        'fields' => [
            'invoice_number' => 'Invoice number',
            'status' => 'Status',
            'payment_method' => 'Payment method',
            'gateway' => 'Gateway',
            'subtotal' => 'Subtotal',
            'fee' => 'Channel fee',
            'total' => 'Customer total',
            'currency' => 'Currency',
            'paid_at' => 'Paid at',
            'expired_at' => 'Expires at',
            'created_at' => 'Created at',
        ],
    ],

    'subscriptions' => [
        'title' => 'Subscriptions',
        'list_title' => 'Subscription list',
        'create_title' => 'Add subscription',
        'edit_title' => 'Edit subscription',
        'show_title' => 'Subscription detail',
        'show_card_title' => 'Subscription :number',
        'add_button' => 'Add subscription',
        'new_card_title' => 'New subscription',
        'edit_card_title' => 'Edit subscription: :number',
        'back_to_list' => 'Back to subscriptions',
        'datatable' => [
            'empty' => 'No subscriptions found.',
            'zero_records' => 'No subscriptions match your search.',
            'columns' => [
                'subscription_number' => 'Subscription #',
                'subscriber_name' => 'Subscriber',
                'plan_name' => 'Plan',
                'status' => 'Status',
                'amount' => 'Amount',
                'actions' => 'Actions',
            ],
        ],
        'fields' => [
            'subscriber_name' => 'Subscriber name',
            'subscriber_email' => 'Subscriber email',
            'subscriber_phone' => 'Subscriber phone',
            'plan_name' => 'Plan name',
            'plan_description' => 'Plan description',
            'billing_cycle' => 'Billing cycle',
            'amount' => 'Amount',
            'currency' => 'Currency',
            'status' => 'Status',
            'auto_renew' => 'Auto renew',
            'started_at' => 'Started at',
            'expires_at' => 'Expires at',
            'trial_ends_at' => 'Trial ends at',
            'created_at' => 'Created at',
        ],
    ],

    'confirm' => [
        'delete_subscription' => 'Delete this subscription?',
    ],

    'ipaymu_return' => [
        'invoice_not_found' => 'We could not match this payment to an invoice.',
        'paid' => 'Payment received. Your invoice is marked as paid.',
        'pending' => 'Payment is being processed. Refresh the page in a moment if the status has not updated.',
        'failed' => 'Payment failed or was declined.',
        'cancelled' => 'Payment was cancelled.',
        'expired' => 'Payment session expired.',
    ],

    'checkout' => [
        'title' => 'Payment',
        'invoice_summary' => 'Invoice :number',
        'amount' => 'Amount due',
        'pay_now' => 'Pay now',
        'snap_loading' => 'Opening payment…',
        'snap_failed' => 'Could not start checkout',
        'started' => 'Checkout session created.',
        'paid' => 'This invoice is already paid.',
        'not_payable' => 'This invoice cannot be paid in its current state.',
        'unsupported_gateway' => 'The invoice payment gateway does not support hosted checkout.',
    ],
];
