<?php

return [
    'title' => '설정',
    'subtitle' => '관리자 UI를 통해 관리되는 운영 구성입니다.',
    'read_only' => '이 설정에 대한 보기 전용 권한이 있습니다.',
    'source' => '현재 소스: :source',
    'groups' => [
        'platform' => '플랫폼',
        'archivd' => 'Archivd',
        'microstock' => 'Microstock',
        'notification_audiences' => '알림 대상',
        'currencies' => '통화',
    ],
    'fields' => [
        // Platform — JWT
        'platform.jwt.ttl' => 'JWT 액세스 토큰 유효 기간(분)',
        'platform.jwt.refresh_ttl' => 'JWT 갱신 창(분)',
        'platform.jwt.remember_ttl' => 'JWT 로그인 유지 유효 기간(분)',
        'platform.jwt.refresh_skew' => 'JWT 자동 갱신 여유 시간(분)',
        'platform.jwt.blacklist_grace_period' => 'JWT 블랙리스트 유예 기간(초)',
        // Platform — auth / 2FA
        'platform.auth.default_role' => '신규 사용자 기본 역할',
        'platform.auth.verification_expire' => '이메일 인증 링크 만료 시간(분)',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'platform.two_factor.window' => '2단계 인증 TOTP 검증 창',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'platform.two_factor.issuer' => '2단계 인증 발급자 이름(인증 앱)',
        // Platform — hCaptcha
        'platform.hcaptcha.enabled' => 'hCaptcha 활성화',
        // Platform — notifications
        'platform.notifications.delivery' => '알림 전달 방식',
        'platform.notifications.polling_interval' => '알림 폴링 간격(초)',
        // Platform — error log
        'platform.errorlog.enabled' => '영구 오류 로그 활성화',
        'platform.errorlog.clean_after_days' => '오류 로그 보관 기간(일)',
        'platform.errorlog.max_message_length' => '오류 로그 최대 메시지 길이',
        'platform.errorlog.max_trace_length' => '오류 로그 최대 트레이스 길이',
        'platform.errorlog.max_context_json_length' => '오류 로그 최대 컨텍스트 JSON 길이',
        // Platform — activity log
        'platform.activitylog.enabled' => '활동 로그 활성화',
        'platform.activitylog.buffer_enabled' => '활동 로그 버퍼 활성화',
        // Platform — localization
        'platform.localization.locale_cookie' => '로케일 쿠키 이름',
        'identity.discord.connections_snapshot_max_age_minutes' => 'Discord 연결 스냅샷 최대 유효 시간(분)',
        'platform.localization.locale_cookie_minutes' => '로케일 쿠키 유효 기간(분)',
        // Platform — payment
        'platform.payment.channel_fee_bearer' => '결제 채널 수수료 부담 주체',
        'platform.payment.reporting_timezone' => '결제 보고 시간대',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'platform.payment.prefer_channel_checkout' => '채널 자체 체크아웃 우선',
        'platform.payment.default_buyer_phone' => '기본 구매자 전화번호(iPaymu 대체값)',
        // Platform — mail identity
        'platform.mail.from_address' => '메일 발신 주소',
        'platform.mail.from_name' => '메일 발신자 이름',
        // Platform — service
        'platform.service.grace_days' => '서비스 유예 일수',
        'platform.service.settlement_currency' => '정산 통화',
        'platform.service.purchase_max_quantity' => '서비스 구매 최대 수량',
        // Platform — inference fallbacks
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'platform.inference.default_provider_slug' => '기본 추론 공급자 슬러그',
        'platform.inference.default_input_per_million_usd' => '기본 입력 요율(1M 토큰당 USD)',
        'platform.inference.default_output_per_million_usd' => '기본 출력 요율(1M 토큰당 USD)',
        'platform.inference.unknown_input_per_million_usd' => '미등록 모델 입력 요율(1M 토큰당 USD)',
        'platform.inference.unknown_output_per_million_usd' => '미등록 모델 출력 요율(1M 토큰당 USD)',
        // Platform — Frankfurter FX
        'platform.frankfurter.cache_ttl' => 'Frankfurter FX 캐시 유효 창(초)',
        'platform.frankfurter.timeout' => 'Frankfurter FX HTTP 시간 초과(초)',
        // Archivd
        'archivd.max_file_size_bytes' => '최대 파일 크기(바이트)',
        'archivd.max_files_per_batch' => '배치당 최대 파일 수',
        'archivd.max_concurrent_uploads' => '최대 동시 업로드 수',
        'archivd.max_concurrent_parts' => '최대 동시 멀티파트 파트 수',
        'archivd.chunk_size_bytes' => '업로드 청크 크기(바이트)',
        'archivd.multipart_threshold_bytes' => '멀티파트 업로드 임계값(바이트)',
        'archivd.max_presigned_parts_per_response' => '응답당 최대 사전 서명 파트 수',
        'archivd.presign_ttl_minutes' => '사전 서명 URL 유효 기간(분)',
        'archivd.session_ttl_hours' => '업로드 세션 유효 기간(시간)',
        'archivd.stale_upload_grace_hours' => '오래된 업로드 유예 기간(시간)',
        'archivd.orphan_inactivity_minutes' => '고아 파트 비활동 시간 초과(분)',
        'archivd.default_quota_bytes' => '기본 스토리지 할당량(바이트)',
        'archivd.base_owned_workspace_slots' => '기본 소유 워크스페이스 슬롯 수',
        'archivd.disabled_regions' => '비활성화된 스토리지 리전',
        'archivd.signed_download_ttl_minutes' => '서명된 다운로드 URL 유효 기간(분)',
        'archivd.signed_download_cache_margin_seconds' => '서명된 다운로드 캐시 여유(초)',
        'archivd.public_download_signature_ttl_minutes' => '공개 다운로드 서명 유효 기간(분)',
        'archivd.allowed_mime_prefixes' => '허용된 MIME 유형 접두사',
        'archivd.download_vault.enabled' => '다운로드 볼트 프록시 활성화',
        // Microstock
        'microstock.daily_free.enabled' => '일일 무료 다운로드 활성화',
        'microstock.daily_free.limit_per_user' => '사용자당 일일 무료 다운로드 한도',
        'microstock.daily_free.timezone' => '일일 무료 초기화 시간대',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'microstock.ai_billing.markup_multiplier' => 'AI 과금 마크업 배율',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'microstock.ai_billing.estimated_billable_micro_usd_per_asset' => '에셋당 예상 과금액(마이크로 USD)',
        'microstock.ai_billing.estimate_thinking_attempt_factor' => '사고 시도 추정 계수',
        'microstock.ai.image_max_px' => 'AI 이미지 최대 크기(px)',
        'microstock.ai.image_jpeg_quality' => 'AI 이미지 JPEG 품질',
        'microstock.ai.max_request_image_bytes' => '요청 이미지 최대 크기(바이트)',
        'microstock.ai.http_retries' => 'AI HTTP 재시도 횟수',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'microstock.ai.thinking_visual_attempts' => '사고 시각 시도 횟수',
        'microstock.ai.batch_chunk' => 'AI 배치 청크 크기',
        'microstock.ai.batch_min_files' => 'AI 배치 최소 파일 수',
        'microstock.ai.phase.normal_after' => 'AI 단계: 일반 처리 전환 시간(초)',
        'microstock.ai.phase.grace_after' => 'AI 단계: 유예 처리 전환 시간(초)',
        'microstock.ai.stage.vision_grace_after' => 'AI 단계: 비전 단계 유예 모드 전환 시간(초)',
        'microstock.ai.stage.thinking_grace_after' => 'AI 단계: 사고 단계 유예 모드 전환 시간(초)',
        'microstock.ai.stage.thinking_backlog_after' => 'AI 단계: 사고 작업 백로그 큐 이동 시간(초)',
        'microstock.ai.vision_image.delivery' => '비전 이미지 전달 방식',
        'microstock.ai.vision_image.variant_kind' => '비전 이미지 변형 종류',
        'microstock.ai.vision_image.signed_url_ttl_minutes' => '비전 서명 URL 유효 기간(분)',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'microstock.ai.inference_provider_slug' => 'Microstock 추론 공급자 슬러그',
        'microstock.ai.vision.connection' => '비전 AI 연결(비워두면 기본값)',
        'microstock.ai.thinking.connection' => '사고 AI 연결(비워두면 기본값)',
        'microstock.ai.vision.model' => '비전 모델',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'microstock.ai.vision.free_model' => '비전 무료 티어 모델(비워두면 유료 모델)',
        'microstock.ai.vision.timeout' => '비전 HTTP 시간 초과(초)',
        'microstock.ai.thinking.model' => '사고 모델',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'microstock.ai.thinking.free_model' => '사고 무료 티어 모델(비워두면 유료 모델)',
        'microstock.ai.thinking.timeout' => '사고 HTTP 시간 초과(초)',
        'microstock.ai.upstream.window_seconds' => '업스트림 토큰 예산 창(초)',
        'microstock.ai.upstream.token_budget' => '창당 업스트림 토큰 예산',
        'microstock.ai.upstream.max_concurrent_vision' => '최대 동시 비전 요청 수',
        'microstock.ai.upstream.max_concurrent_thinking' => '최대 동시 사고 요청 수',
        'microstock.ai.upstream.collection_vision_concurrency' => '컬렉션 비전 동시 처리 수',
        'microstock.ai.upstream.collection_thinking_concurrency' => '컬렉션 사고 동시 처리 수',
        'microstock.ai.upstream.solo_vision_concurrency' => '단일 비전 동시 처리 수',
        'microstock.ai.upstream.solo_thinking_concurrency' => '단일 사고 동시 처리 수',
        'microstock.ai.upstream.release_after_seconds' => '업스트림 슬롯 해제 시간(초)',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'microstock.csv_disk' => 'CSV 내보내기 디스크',
    ],
    'hints' => [
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'platform.mail.from_address' => '발신 메일의 전역 발신자 주소입니다(전송 자격 증명은 .env에 유지됩니다).',
        'platform.payment.default_buyer_phone' => '구매자 프로필에 전화번호가 없을 때 사용하는 대체 번호입니다(iPaymu 요구 사항).',
        'platform.frankfurter.timeout' => 'Frankfurter FX 조회의 HTTP 시간 초과입니다.',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'microstock.ai.vision.free_model' => '선택적 무료 티어 모델 재정의입니다. 비워두면 유료 비전 모델을 상속합니다.',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'microstock.ai.thinking.free_model' => '선택적 무료 티어 모델 재정의입니다. 비워두면 유료 사고 모델을 상속합니다.',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'microstock.ai.vision.connection' => '비전 단계가 사용할 AI 연결 슬러그(설정 → AI 연결)입니다. 비워두면 기본 연결을 사용합니다.',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'microstock.ai.thinking.connection' => '사고 단계가 사용할 AI 연결 슬러그(설정 → AI 연결)입니다. 비워두면 기본 연결을 사용합니다.',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'microstock.ai.upstream.window_seconds' => '업스트림 토큰 예산의 기준 창입니다(실제 제한은 동시 처리 상한으로 적용됩니다).',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'platform.jwt.refresh_skew' => '액세스 토큰이 만료되기 전에 지정한 시간(분)만큼 미리 갱신합니다.',
        'platform.notifications.delivery' => '실시간 모드는 WebSocket을 사용하며 구성된 PUSHER_* / 브로드캐스트 자격 증명이 필요합니다. 그렇지 않으면 알림 벨이 HTTP 폴링으로 대체됩니다.',
        'platform.payment.channel_fee_bearer' => '결제 채널 수수료를 부담하는 주체: 판매자 또는 고객입니다.',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'platform.inference.default_provider_slug' => '요청에 공급자가 지정되지 않은 경우 사용하는 대체 공급자입니다.',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'platform.inference.default_input_per_million_usd' => '카탈로그 가격이 없는 등록 모델에 대한 임시 과금 요율입니다.',
        // curated at 2026-09-23T11:04:35+07:00 — ko curation wave
        'platform.inference.unknown_input_per_million_usd' => '미등록 모델에 대한 임시 과금 요율입니다.',
        'microstock.ai.phase.normal_after' => '에셋이 빠른 처리에서 일반 처리로 전환되기까지의 초입니다.',
        'microstock.ai.phase.grace_after' => '유예 기간 처리가 시작되기까지의 초입니다.',
        'microstock.ai.stage.vision_grace_after' => '비전 단계가 유예 모드에 진입하기까지의 초입니다.',
        'microstock.ai.stage.thinking_grace_after' => '사고 단계가 유예 모드에 진입하기까지의 초입니다.',
        'microstock.ai.stage.thinking_backlog_after' => '사고 작업이 백로그 큐로 이동하기까지의 초입니다.',
        // curated at 2026-09-23T12:15:01+07:00 — ko curation wave: claim verification unified on 검증
        // curated at 2026-09-23T12:15:01+07:00 — ko curation wave: claim verification unified on 검증
        // curated at 2026-09-23T12:15:01+07:00 — ko curation wave: claim verification unified on 검증
        // curated at 2026-09-27T19:30:00+07:00 — ko curation wave
        'identity.discord.connections_snapshot_max_age_minutes' => '저장된 Discord 연결 스냅샷이 신뢰되는 기간입니다. 확인 실패 시 이 기간이 지나면 실시간 Discord API 새로고침이 트리거됩니다.',
    ],
    'options' => [
        'platform' => [
            'notifications' => [
                'delivery' => [
                    'polling' => 'HTTP 폴링',
                    'realtime' => 'WebSocket(실시간)',
                ],
            ],
        ],
    ],
];
