<?php

declare(strict_types=1);

return [
    // Gate / middleware
    'auth_required' => 'المصادقة مطلوبة.',
    'unknown_product' => 'نطاق منتج غير معروف: :product.',
    'forbidden' => 'نطاق المنتج هذا خاص. يقتصر الوصول عليه على Root وAdmin ومختبري الإصدار التجريبي.',

    // Admin API
    'list_success' => 'تم جلب إعدادات الوصول إلى نطاقات المنتجات.',
    // curated at 2026-09-22T21:21:19+07:00 — ar curation wave 2
    'updated' => 'تم تحديث رؤية نطاق المنتج.',
    'invalid_visibility' => 'يجب أن تكون الرؤية عامة أو خاصة.',

    // UI
    'title' => 'الوصول إلى نطاقات المنتجات',
    // curated at 2026-09-22T21:21:19+07:00 — ar curation wave 2
    'subtitle' => 'تحكّم في نطاقات المنتجات العامة أو المقتصرة على مختبري الإصدار التجريبي.',
    'empty' => 'لم يتم تسجيل أي نطاقات منتجات.',
    'col_domain' => 'نطاق المنتج',
    'col_visibility' => 'الرؤية',
    'visibility_public' => 'عام',
    'visibility_private' => 'خاص',
    'public_hint' => 'يمكن لأي شخص الوصول إلى نطاق المنتج هذا.',
    'private_hint' => 'يمكن لـ Root وAdmin ومختبري الإصدار التجريبي فقط الوصول إليه.',
    'beta_role' => 'دور الإصدار التجريبي: :role',
    'toggle_updated' => 'تم تحديث الرؤية.',
    // curated at 2026-09-22T21:21:19+07:00 — ar curation wave 2
    'toggle_failed' => 'تعذر تحديث الرؤية.',
];
