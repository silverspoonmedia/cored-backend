<?php

declare(strict_types=1);

return [
    'list_success' => '제품 활성화 목록을 불러왔습니다.',
    'show_success' => '제품 활성화를 불러왔습니다.',
    'admin_list_success' => '제품 활성화 목록을 불러왔습니다.',
    'stats_success' => '제품 활성화 통계를 불러왔습니다.',
    // curated at 2026-09-23T13:30:13+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
    'suspended' => '제품 활성화가 정지되었습니다.',
    'reactivated' => '제품 활성화가 재활성화되었습니다.',
    'approved' => '제품 활성화가 승인되었습니다.',
    'rejected' => '제품 활성화가 거부되었습니다.',

    'not_found' => ':product의 제품 활성화를 찾을 수 없습니다.',
    // curated at 2026-09-23T11:01:13+07:00 — ko curation wave
    'unknown_product' => '알 수 없는 제품 키: :product.',
    'already_active' => '제품이 이미 활성 상태입니다.',
    'cannot_approve' => ':state 상태의 활성화는 승인할 수 없습니다.',
    'cannot_reject' => ':state 상태의 활성화는 거부할 수 없습니다.',
    // curated at 2026-09-23T13:30:13+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
    'cannot_suspend' => ':state 상태의 활성화는 정지할 수 없습니다.',
    'cannot_reactivate' => ':state 상태의 활성화는 재활성화할 수 없습니다.',
    // curated at 2026-09-23T13:52:31+07:00 — ko curation wave: parenthesised particle after a placeholder (the value's final consonant is unknown)
    'type_mismatch' => '제품 :product은(는) 활성화 유형 :expected이(가) 필요하지만 :actual이(가) 전달되었습니다.',
    // curated at 2026-09-23T13:52:31+07:00 — ko curation wave: parenthesised particle after a placeholder (the value's final consonant is unknown)
    'inactive' => '제품 :product이(가) :state 상태입니다. 접근이 거부되었습니다.',
    // curated at 2026-09-23T13:52:31+07:00 — ko curation wave: parenthesised particle after a placeholder (the value's final consonant is unknown)
    'not_provisioned' => '제품 :product이(가) 아직 프로비저닝되지 않았습니다.',
    'auth_required' => '인증이 필요합니다.',

    // UI labels (Blade)
    'title' => '제품 활성화',
    'subtitle' => '사용자별 제품 활성화 레지스트리입니다.',
    'col_user' => '사용자',
    'col_product' => '제품',
    'col_type' => '유형',
    'col_state' => '상태',
    'col_source' => '출처',
    // curated at 2026-09-23T11:01:13+07:00 — ko curation wave
    'col_activated_at' => '활성화일시',
    // curated at 2026-09-23T13:30:13+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
    'col_suspended_at' => '정지일시',
    // curated at 2026-09-23T11:01:13+07:00 — ko curation wave
    'col_expired_at' => '만료일시',
    // curated at 2026-09-23T11:01:13+07:00 — ko curation wave
    'col_updated_at' => '수정일시',
    'col_actions' => '작업',
    'filter_product' => '제품',
    'filter_state' => '상태',
    'filter_all' => '전체',
    // curated at 2026-09-23T13:30:13+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
    'action_suspend' => '정지',
    'action_reactivate' => '재활성화',
    'action_approve' => '승인',
    'action_reject' => '거부',
    'reason_label' => '사유',
    // curated at 2026-09-23T11:01:13+07:00 — ko curation wave
    'reason_placeholder' => '이 작업의 사유(선택 사항)',
    'state_pending' => '대기 중',
    'state_active' => '활성',
    // curated at 2026-09-23T13:30:13+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
    'state_suspended' => '정지됨',
    'state_expired' => '만료됨',
    'type_implicit' => '암시적',
    'type_explicit' => '명시적',

    // v2 — per-product insights UI
    'products_title' => '제품 도메인',
    // curated at 2026-09-23T13:30:13+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
    'products_subtitle' => '제품 도메인별 인사이트와 제어 기능입니다.',
    'products_empty' => '등록된 제품 도메인이 없습니다.',
    'products_list_success' => '제품 요약을 불러왔습니다.',
    'product_show_success' => '제품 상세를 불러왔습니다.',
    'user_detail_success' => '사용자 제품 상세를 불러왔습니다.',
    'product_title' => '제품: :product',
    'view_product' => '인사이트 보기',
    'back_to_products' => '제품 목록으로 돌아가기',
    'fulfillment_target' => '이행 대상',
    'no_activations' => '이 제품에 대한 활성화가 없습니다.',
    'pagination' => '활성화 페이지네이션',

    // Metric labels (keys mirror ProductInsightService output)
    'metric_total_storage_used_bytes' => '총 스토리지 사용량',
    'metric_total_files' => '총 파일 수',
    'metric_total_workspaces' => '총 워크스페이스 수',
    // curated at 2026-09-23T13:30:13+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
    'metric_paid_users_count' => '유료 티어 사용자',
    // curated at 2026-09-23T13:30:13+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
    'metric_free_users_count' => '무료 티어 사용자',
    // curated at 2026-09-23T13:30:13+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
    'metric_grace_users_count' => '유예 티어 사용자',
    'metric_storage_used_bytes' => '스토리지 사용량',
    'metric_file_count' => '파일 수',
    'metric_workspace_count_owned' => '워크스페이스 수',

    // Tier labels
    // curated at 2026-09-23T13:30:13+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
    'tier' => '티어',
    'tier_free' => '무료',
    'tier_paid' => '유료',
    'tier_grace' => '유예',
    // curated at 2026-09-23T13:30:13+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
    'tier_n/a' => 'N/A',
];
