<?php

return [
    'success' => '결제가 완료되었습니다.',
    // curated at 2026-09-23T13:18:49+07:00 — ko curation wave: register normalized to 해요체
    'failed' => '결제에 실패했습니다. 다시 시도하세요.',
    'pending' => '결제를 처리하고 있습니다.',

    'gateways' => [
        'list' => '결제 게이트웨이를 불러왔습니다.',
        'show' => '결제 게이트웨이를 불러왔습니다.',
        'created' => '결제 게이트웨이가 생성되었습니다.',
        'updated' => '결제 게이트웨이가 수정되었습니다.',
        'deleted' => '결제 게이트웨이가 삭제되었습니다.',
        'inactive' => '선택한 결제 게이트웨이가 활성화되어 있지 않습니다.',
        'none_active' => '활성화된 결제 게이트웨이가 설정되어 있지 않습니다.',
    ],

    'invoices' => [
        'list' => '인보이스를 불러왔습니다.',
        'show' => '인보이스를 불러왔습니다.',
        // curated at 2026-09-23T11:22:12+07:00 — ko curation wave
        'checkout_channels' => '체크아웃 결제 채널을 불러왔습니다.',
        'checkout_channels_not_payable' => '결제할 수 없는 인보이스입니다.',
    ],

    'income' => [
        'overview' => '결제 수입 개요를 불러왔습니다.',
        'invoices' => '결제 수입 인보이스를 불러왔습니다.',
    ],

    'subscriptions' => [
        'list' => '구독을 불러왔습니다.',
        'show' => '구독을 불러왔습니다.',
        'created' => '구독이 생성되었습니다.',
        'updated' => '구독이 수정되었습니다.',
        'deleted' => '구독이 삭제되었습니다.',
    ],

    'midtrans' => [
        'snap_created' => 'Snap 트랜잭션이 생성되었습니다.',
        // curated at 2026-09-23T11:22:12+07:00 — ko curation wave
        'charge_created' => 'Core API 결제가 생성되었습니다.',
        'status_retrieved' => '트랜잭션 상태를 불러왔습니다.',
        'cancel_ok' => '트랜잭션이 취소되었습니다.',
        'expire_ok' => '트랜잭션이 만료되었습니다.',
        'refund_ok' => '환불이 처리되었습니다.',
        'approve_ok' => '트랜잭션이 승인되었습니다.',
        'deny_ok' => '트랜잭션이 거부되었습니다.',
        'sync_ok' => '인보이스 상태가 Midtrans와 동기화되었습니다.',
        'invoice_not_midtrans' => '인보이스가 Midtrans 게이트웨이에 연결되어 있지 않습니다.',
        // curated at 2026-09-23T11:22:12+07:00 — ko curation wave
        'invoice_not_payable' => '결제를 시작하려면 인보이스 상태가 pending 또는 challenge여야 합니다.',
        // curated at 2026-09-23T11:22:12+07:00 — ko curation wave
        'external_sync_failed' => '체크아웃을 시작하기 전에 Midtrans에서 결제 상태를 확인할 수 없었습니다.',
        'webhook_invalid_json' => '유효하지 않은 JSON 본문입니다.',
        'webhook_invalid_signature' => '유효하지 않은 알림 서명입니다.',
        'webhook_missing_order' => '알림에 order_id가 없습니다.',
        'webhook_ack_no_status' => '알림을 확인했습니다 (상태를 확인할 수 없음).',
        'webhook_orphan' => '알림을 수락했습니다 (일치하는 인보이스 없음).',
        'webhook_ok' => '알림이 처리되었습니다.',
        // curated at 2026-09-23T13:18:49+07:00 — ko curation wave: register normalized to 해요체
        'webhook_ping' => 'Midtrans 웹훅 URL에 연결할 수 있습니다 (알림에는 POST를 사용하세요).',
        'webhook_subscription_ack' => '구독 알림을 확인했습니다.',
        'open_api_public_key_missing' => 'Midtrans Open API 공개 키가 설정되어 있지 않습니다.',
        'open_api_signature_invalid' => 'Open API 알림의 X-SIGNATURE가 유효하지 않거나 검증할 수 없습니다.',
    ],

    'checkout' => [
        // curated at 2026-09-23T13:18:49+07:00 — ko curation wave: register normalized to 해요체
        'active_session_pending' => '이전 결제 세션이 아직 공급자에서 처리 중입니다. 만료되거나 완료될 때까지 기다린 후 채널을 변경하세요.',
        // curated at 2026-09-23T11:22:12+07:00 — ko curation wave
        'gateway_provider_mismatch' => '인보이스의 결제 게이트웨이가 요청한 체크아웃 공급자와 일치하지 않습니다.',
    ],

    'channels' => [
        'list' => '결제 채널을 불러왔습니다.',
        'show' => '결제 채널을 불러왔습니다.',
        'updated' => '결제 채널이 수정되었습니다.',
        'checkout_created' => '결제 안내가 생성되었습니다.',
        'checkout_failed' => '이 채널의 모든 결제 경로가 실패했습니다.',
        'route_updated' => '결제 채널 경로가 수정되었습니다.',
        'not_found' => '결제 채널을 찾을 수 없습니다.',
        'not_available' => '선택한 결제 채널을 사용할 수 없습니다.',
        'unsupported_gateway' => '이 채널에 지원되는 결제 게이트웨이가 설정되어 있지 않습니다.',
        'policy_managed_route' => '이 경로는 라우팅 정책에 의해 관리됩니다. 활성화만 변경할 수 있습니다.',
    ],

    'ipaymu' => [
        // curated at 2026-09-23T11:22:12+07:00 — ko curation wave
        'snap_created' => 'iPaymu 호스팅 체크아웃 세션이 생성되었습니다.',
        'charge_created' => 'iPaymu 직접 결제가 생성되었습니다.',
        'status_retrieved' => 'iPaymu에서 트랜잭션 상태를 불러왔습니다.',
        'sync_ok' => '인보이스 상태가 iPaymu와 동기화되었습니다.',
        'invoice_not_ipaymu' => '인보이스가 iPaymu 게이트웨이에 연결되어 있지 않습니다.',
        // curated at 2026-09-23T11:22:12+07:00 — ko curation wave
        'invoice_not_payable' => '결제를 시작하려면 인보이스 상태가 pending 또는 challenge여야 합니다.',
        // curated at 2026-09-23T11:22:12+07:00 — ko curation wave
        'external_sync_failed' => '체크아웃을 시작하기 전에 iPaymu에서 결제 상태를 확인할 수 없었습니다.',
        // curated at 2026-09-23T13:18:49+07:00 — ko curation wave: register normalized to 해요체
        'active_payment_exists' => '이 인보이스에 대해 진행 중인 결제 세션이 이미 있습니다. 새 체크아웃을 시작하기 전에 완료하거나 취소하세요.',
        'invoice_not_found' => '이 주문 번호에 해당하는 iPaymu 인보이스를 찾을 수 없습니다.',
        // curated at 2026-09-23T13:18:49+07:00 — ko curation wave: register normalized to 해요체
        'missing_transaction_id' => '인보이스에 아직 iPaymu 트랜잭션 ID가 없습니다. 먼저 결제 세션을 완료하세요.',
        'buyer_required' => '요청, 인보이스 대상 또는 인증된 사용자 프로필에서 구매자 정보를 확인할 수 없습니다.',
        'webhook_invalid_json' => '유효하지 않은 JSON 본문입니다.',
        'webhook_invalid_signature' => '유효하지 않은 알림 서명입니다.',
        'webhook_invalid_va' => 'VA 헤더가 설정된 iPaymu VA와 일치하지 않습니다.',
        'webhook_ack_no_status' => '알림을 확인했습니다 (상태를 확인할 수 없음).',
        'webhook_orphan' => '알림을 수락했습니다 (일치하는 인보이스 없음).',
        'webhook_ok' => '알림이 처리되었습니다.',
        // curated at 2026-09-23T13:18:49+07:00 — ko curation wave: register normalized to 해요체
        'webhook_ping' => 'iPaymu 웹훅 URL에 연결할 수 있습니다 (알림에는 POST를 사용하세요).',
    ],
];
