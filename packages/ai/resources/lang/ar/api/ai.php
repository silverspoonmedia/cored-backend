<?php

return [
    'connection' => [
        // curated at 2026-09-22T20:32:26+07:00 — ar curation wave
        'list_success' => 'تم جلب اتصالات الذكاء الاصطناعي بنجاح.',
        // curated at 2026-09-22T20:32:26+07:00 — ar curation wave
        'show_success' => 'تم جلب اتصال الذكاء الاصطناعي بنجاح.',
        'created' => 'تم إنشاء اتصال الذكاء الاصطناعي. شغّل الاختبار لتفعيله.',
        'updated' => 'تم تحديث اتصال الذكاء الاصطناعي. شغّل الاختبار لتفعيله مرة أخرى.',
        'deleted' => 'تم حذف اتصال الذكاء الاصطناعي بنجاح.',
        'default_updated' => 'تم تحديث اتصال الذكاء الاصطناعي الافتراضي.',
        'slug_required' => 'معرّف الاتصال مطلوب.',
        'slug_taken' => 'معرّف الاتصال هذا مستخدم بالفعل.',
        'label_required' => 'اسم الاتصال مطلوب.',
        'endpoint_invalid' => 'يجب أن تكون نقطة النهاية رابط http(s) صالحًا.',
        'test_model_required' => 'يلزم نموذج اختبار لفحص هذا الاتصال.',
        'payload_invalid' => 'الحمولة السرية المخزّنة غير صالحة. أعد إدخال مفتاح API.',
        'payload_missing' => 'الحمولة السرية تفتقد الحقل المطلوب: :field.',
        // curated at 2026-09-22T20:32:26+07:00 — ar curation wave
        'payload_unknown_key' => 'حقل سري غير متوقع: :field.',
        'default_requires_active' => 'لا يمكن تعيين الاتصال الافتراضي إلا لاتصال نشط (تم اختباره).',
        'delete_requires_default_handover' => 'عيّن اتصالًا آخر كافتراضي قبل حذف هذا الاتصال.',
        'test_not_configured' => 'الاتصال يفتقد نقطة النهاية أو مفتاح API أو نموذج الاختبار.',
        'test_unreachable' => 'تعذّر الوصول إلى نقطة النهاية: :error',
        'test_unauthorized' => 'رفضت نقطة النهاية مفتاح API (401/403).',
        'test_http_failed' => 'أجابت نقطة النهاية بـ HTTP :status.',
        'test_http_failed_with_body' => 'أجابت نقطة النهاية بـ HTTP :status: :detail',
        'test_passed' => 'نجح اختبار الاتصال.',
    ],
];
