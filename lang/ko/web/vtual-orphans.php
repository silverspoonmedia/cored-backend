<?php

return [
    'title' => '고아 vTuber',
    'breadcrumb' => '고아 vTuber',
    'list_title' => '고아 vTuber 프로필',
    'subtitle' => '스태프가 등록한 소유자 없는 프로필입니다. 클레임된 프로필은 이 목록에서 숨겨집니다.',
    'add_button' => '고아 vTuber 추가',
    'create_title' => '고아 vTuber 추가',
    'edit_title' => '고아 vTuber 수정',
    'form' => [
        'display_name' => '표시 이름',
        'slug' => '슬러그',
        'slug_hint' => '선택 사항입니다. 소문자, 숫자, 하이픈만 사용할 수 있습니다.',
        'bio' => '소개',
        'avatar_url' => '아바타 URL',
        'submit_create' => '고아 생성',
        'submit_update' => '변경 사항 저장',
    ],
    'datatable' => [
        'empty' => '아직 고아 vTuber 프로필이 없습니다.',
        'zero_records' => '일치하는 프로필이 없습니다.',
        'confirm_delete' => '이 고아 프로필을 보관 처리하시겠습니까? 더 이상 클레임할 수 없게 됩니다.',
        'columns' => [
            'display_name' => '표시 이름',
            'slug' => '슬러그',
            'channels' => '채널',
            // curated at 2026-09-23T11:04:36+07:00 — ko curation wave
            'created_at' => '생성일',
            'actions' => '작업',
        ],
    ],
    'channels' => [
        'section_title' => '플랫폼 채널',
        // curated at 2026-09-23T13:30:12+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
        'section_help' => '일괄 해석할 채널 프로필을 추가하세요(즉시 API 호출 없음). 플랫폼당 채널 50개가 대기열에 쌓이거나 15분이 지나면 자동으로 해석됩니다.',
        'avatar' => '아바타',
        'platform' => '플랫폼',
        'profile' => '채널 프로필',
        // curated at 2026-09-23T13:31:29+07:00 — ko curation wave: handle is 핸들 (same as the handle column), not the Latin handle
        'profile_help' => '플랫폼 채널 ID, @핸들, 로그인 또는 프로필 URL.',
        // curated at 2026-09-23T13:30:12+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
        'profile_help_youtube' => 'YouTube: UC 채널 ID, @핸들 또는 youtube.com/… 프로필 URL.',
        'profile_help_twitch' => 'Twitch: 숫자 사용자 ID, 로그인 또는 twitch.tv/… 프로필 URL.',
        'profile_placeholder_youtube' => 'UC… / @handle / https://youtube.com/@handle',
        'profile_placeholder_twitch' => '12345678 / login / https://twitch.tv/login',
        'profile_or_id' => '프로필 / 외부 ID',
        'handle' => '핸들',
        'status' => '상태',
        'add_button' => '채널 추가',
        'table_caption' => '고아 vTuber 채널',
        'empty' => '아직 등록된 채널이 없습니다.',
        // curated at 2026-09-23T13:30:12+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
        'status_pending' => '해석 대기 중',
        'status_active' => '활성',
        // curated at 2026-09-23T13:30:12+07:00 — ko curation wave: alignment pass (term unification, register, cross-side parity)
        'status_disconnected' => '연결 끊김',
        'status_archived' => '보관됨',
    ],
];
