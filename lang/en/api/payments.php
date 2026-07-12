<?php

return [
    'success' => 'Payment completed successfully.',
    'failed' => 'Payment failed. Please try again.',
    'pending' => 'Payment is being processed.',

    'gateways' => [
        'list' => 'Payment gateways retrieved successfully.',
        'show' => 'Payment gateway retrieved successfully.',
        'created' => 'Payment gateway created successfully.',
        'updated' => 'Payment gateway updated successfully.',
        'deleted' => 'Payment gateway deleted successfully.',
        'inactive' => 'The selected payment gateway is not active.',
        'none_active' => 'No active payment gateway is configured.',
    ],

    'invoices' => [
        'list' => 'Invoices retrieved successfully.',
        'show' => 'Invoice retrieved successfully.',
    ],

    'subscriptions' => [
        'list' => 'Subscriptions retrieved successfully.',
        'show' => 'Subscription retrieved successfully.',
        'created' => 'Subscription created successfully.',
        'updated' => 'Subscription updated successfully.',
        'deleted' => 'Subscription deleted successfully.',
    ],

    'midtrans' => [
        'snap_created' => 'Snap transaction created.',
        'charge_created' => 'Core API charge created.',
        'status_retrieved' => 'Transaction status retrieved.',
        'cancel_ok' => 'Transaction cancelled.',
        'expire_ok' => 'Transaction expired.',
        'refund_ok' => 'Refund processed.',
        'approve_ok' => 'Transaction approved.',
        'deny_ok' => 'Transaction denied.',
        'sync_ok' => 'Invoice status synchronized with Midtrans.',
        'invoice_not_midtrans' => 'Invoice is not linked to the Midtrans gateway.',
        'invoice_not_payable' => 'Invoice must be pending or challenge to start payment.',
        'external_sync_failed' => 'Could not verify payment status with Midtrans before starting checkout.',
        'webhook_invalid_json' => 'Invalid JSON body.',
        'webhook_invalid_signature' => 'Invalid notification signature.',
        'webhook_missing_order' => 'Missing order_id in notification.',
        'webhook_ack_no_status' => 'Notification acknowledged (status could not be resolved).',
        'webhook_orphan' => 'Notification accepted (no matching invoice).',
        'webhook_ok' => 'Notification processed.',
        'webhook_ping' => 'Midtrans webhook URL is reachable (use POST for notifications).',
        'webhook_subscription_ack' => 'Subscription notification acknowledged.',
        'open_api_public_key_missing' => 'Midtrans Open API public key is not configured.',
        'open_api_signature_invalid' => 'Invalid or unverifiable X-SIGNATURE for Open API notification.',
    ],

    'checkout' => [
        'active_session_pending' => 'The previous payment session is still pending at the provider. Wait for it to expire or complete before switching channels.',
        'gateway_provider_mismatch' => 'Invoice payment gateway does not match the requested checkout provider.',
    ],

    'channels' => [
        'list' => 'Payment channels retrieved successfully.',
        'show' => 'Payment channel retrieved successfully.',
        'updated' => 'Payment channel updated successfully.',
        'checkout_created' => 'Payment instructions created.',
        'checkout_failed' => 'All payment routes for this channel failed.',
        'route_updated' => 'Payment channel route updated.',
        'not_found' => 'Payment channel not found.',
        'not_available' => 'The selected payment channel is not available.',
        'unsupported_gateway' => 'No supported payment gateway is configured for this channel.',
    ],

    'ipaymu' => [
        'snap_created' => 'iPaymu hosted checkout session created.',
        'charge_created' => 'iPaymu direct payment created.',
        'status_retrieved' => 'Transaction status retrieved from iPaymu.',
        'sync_ok' => 'Invoice status synchronized with iPaymu.',
        'invoice_not_ipaymu' => 'Invoice is not linked to the iPaymu gateway.',
        'invoice_not_payable' => 'Invoice must be pending or challenge to start payment.',
        'external_sync_failed' => 'Could not verify payment status with iPaymu before starting checkout.',
        'active_payment_exists' => 'A payment session is already in progress for this invoice. Complete or cancel it before starting a new checkout.',
        'invoice_not_found' => 'No iPaymu invoice found for this order id.',
        'missing_transaction_id' => 'Invoice has no iPaymu transaction id yet; complete a payment session first.',
        'buyer_required' => 'Provide buyer details or attach the invoice to an order/subscription with customer fields.',
        'webhook_invalid_json' => 'Invalid JSON body.',
        'webhook_invalid_signature' => 'Invalid notification signature.',
        'webhook_invalid_va' => 'VA header does not match configured iPaymu VA.',
        'webhook_ack_no_status' => 'Notification acknowledged (status could not be resolved).',
        'webhook_orphan' => 'Notification accepted (no matching invoice).',
        'webhook_ok' => 'Notification processed.',
        'webhook_ping' => 'iPaymu webhook URL is reachable (use POST for notifications).',
    ],
];
