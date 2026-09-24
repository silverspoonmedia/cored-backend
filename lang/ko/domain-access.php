<?php

declare(strict_types=1);

return [
    // Gate / middleware
    'auth_required' => '인증이 필요합니다.',
    'unknown_product' => '알 수 없는 제품 도메인: :product.',
    'forbidden' => '이 제품 도메인은 비공개입니다. Root, Admin 및 베타 테스터만 접근할 수 있습니다.',

    // Admin API
    'list_success' => '제품 도메인 접근 설정을 불러왔습니다.',
    // curated at 2026-09-23T14:05:28+07:00 — ko curation wave - visibility term unification
    'updated' => '제품 도메인 공개 범위가 업데이트되었습니다.',
    // curated at 2026-09-23T14:05:28+07:00 — ko curation wave - visibility term unification
    'invalid_visibility' => '공개 범위는 public 또는 private이어야 합니다.',

    // UI
    'title' => '제품 도메인 접근',
    'subtitle' => '어떤 제품 도메인을 공개할지, 베타 테스터에게만 제한할지 관리합니다.',
    'empty' => '등록된 제품 도메인이 없습니다.',
    'col_domain' => '제품 도메인',
    // curated at 2026-09-23T14:05:28+07:00 — ko curation wave - visibility term unification
    'col_visibility' => '공개 범위',
    'visibility_public' => '공개',
    'visibility_private' => '비공개',
    'public_hint' => '누구나 이 제품 도메인에 접근할 수 있습니다.',
    'private_hint' => 'Root, Admin 및 베타 테스터만 접근할 수 있습니다.',
    'beta_role' => '베타 역할: :role',
    // curated at 2026-09-23T14:05:28+07:00 — ko curation wave - visibility term unification
    'toggle_updated' => '공개 범위가 업데이트되었습니다.',
    // curated at 2026-09-23T14:05:28+07:00 — ko curation wave - visibility term unification
    'toggle_failed' => '공개 범위를 업데이트할 수 없습니다.',
];
