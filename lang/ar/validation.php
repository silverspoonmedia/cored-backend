<?php

declare(strict_types=1);

return [
    'accepted'               => 'يجب قبول :attribute.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'accepted_if'            => 'يجب قبول :attribute عندما تكون قيمة :other هي :value.',
    'active_url'             => 'حقل :attribute لا يُمثّل رابطًا صحيحًا.',
    'after'                  => 'يجب على حقل :attribute أن يكون تاريخًا لاحقًا للتاريخ :date.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'after_or_equal'         => 'يجب أن يكون حقل :attribute تاريخًا لاحقًا أو مطابقًا للتاريخ :date.',
    'alpha'                  => 'يجب أن لا يحتوي حقل :attribute سوى على حروف.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'alpha_dash'             => 'يجب أن يحتوي حقل :attribute على الحروف والأرقام والشرطات والشرطات السفلية فقط.',
    'alpha_num'              => 'يجب أن يحتوي حقل :attribute على حروفٍ وأرقامٍ فقط.',
    'any_of'                 => 'الحقل :attribute غير صالح.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'array'                  => 'يجب أن يكون حقل :attribute مصفوفة.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'ascii'                  => 'يجب أن يحتوي الحقل :attribute على أحرف أبجدية رقمية ورموز أحادية البايت فقط.',
    'before'                 => 'يجب على حقل :attribute أن يكون تاريخًا سابقًا للتاريخ :date.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'before_or_equal'        => 'يجب أن يكون حقل :attribute تاريخًا سابقًا أو مطابقًا للتاريخ :date.',
    'between'                => [
        'array'   => 'يجب أن يحتوي حقل :attribute على عدد من العناصر بين :min و :max.',
        'file'    => 'يجب أن يكون حجم ملف حقل :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute بين :min و :max.',
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'string'  => 'يجب أن يكون عدد حروف نص حقل :attribute بين :min و :max.',
    ],
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'boolean'                => 'يجب أن تكون قيمة حقل :attribute إما true أو false.',
    'can'                    => 'الحقل :attribute يحتوي على قيمة غير مصرّح بها.',
    'confirmed'              => 'حقل التأكيد غير مُطابق للحقل :attribute.',
    'contains'               => 'الحقل :attribute يفتقد قيمة مطلوبة.',
    'current_password'       => 'كلمة المرور غير صحيحة.',
    'date'                   => 'حقل :attribute ليس تاريخًا صحيحًا.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'date_equals'            => 'يجب أن يكون حقل :attribute مطابقًا للتاريخ :date.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'date_format'            => 'لا يتوافق حقل :attribute مع الصيغة :format.',
    'decimal'                => 'يجب أن يحتوي الحقل :attribute على :decimal منزلة/منازل عشرية.',
    'declined'               => 'يجب رفض :attribute.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'declined_if'            => 'يجب رفض :attribute عندما تكون قيمة :other هي :value.',
    'different'              => 'يجب أن يكون الحقلان :attribute و :other مُختلفين.',
    'digits'                 => 'يجب أن يحتوي حقل :attribute على :digits رقمًا/أرقام.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'digits_between'         => 'يجب أن يحتوي حقل :attribute على عدد من الأرقام بين :min و :max.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'dimensions'             => 'الحقل :attribute يحتوي على أبعاد صورة غير صالحة.',
    'distinct'               => 'للحقل :attribute قيمة مُكرّرة.',
    'doesnt_contain'         => 'يجب ألا يحتوي الحقل :attribute على أي مما يلي: :values.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'doesnt_end_with'        => 'يجب ألا ينتهي الحقل :attribute بأحد القيم التالية: :values.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'doesnt_start_with'      => 'يجب ألا يبدأ الحقل :attribute بأحد القيم التالية: :values.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'email'                  => 'يجب أن يكون حقل :attribute عنوان بريد إلكتروني صالحًا.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'encoding'               => 'يجب أن يكون الحقل :attribute مُرمَّزًا بـ :encoding.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'ends_with'              => 'يجب أن ينتهي حقل :attribute بأحد القيم التالية: :values.',
    'enum'                   => 'قيمة حقل :attribute غير موجودة في قائمة القيم المسموح بها.',
    'exists'                 => 'قيمة الحقل :attribute غير موجودة.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'extensions'             => 'يجب أن يحتوي الحقل :attribute على أحد الامتدادات التالية: :values.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'file'                   => 'يجب أن يكون الحقل :attribute ملفًا.',
    'filled'                 => 'حقل :attribute إجباري.',
    'gt'                     => [
        'array'   => 'يجب أن يحتوي حقل :attribute على أكثر من :value عناصر/عنصر.',
        'file'    => 'يجب أن يكون حجم ملف حقل :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أكبر من :value.',
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'string'  => 'يجب أن يكون طول نص حقل :attribute أكثر من :value حروفٍ/حرفًا.',
    ],
    'gte'                    => [
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'array'   => 'يجب أن يحتوي حقل :attribute على الأقل على :value عنصرًا/عناصر.',
        'file'    => 'يجب أن يكون حجم ملف حقل :attribute على الأقل :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute مساوية أو أكبر من :value.',
        'string'  => 'يجب أن يكون طول نص حقل :attribute على الأقل :value حروفٍ/حرفًا.',
    ],
    // curated at 2026-09-21T15:32:00+07:00 — added key (missing vs en/id/ja/zh_CN)
    'hcaptcha'               => 'فشل التحقق من الكابتشا. يرجى المحاولة مرة أخرى.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'hex_color'              => 'يجب أن يحتوي الحقل :attribute على صيغة لون HEX صالحة.',
    'image'                  => 'يجب أن يكون حقل :attribute صورةً.',
    'in'                     => 'قيمة حقل :attribute غير موجودة في قائمة القيم المسموح بها.',
    'in_array'               => 'حقل :attribute غير موجود في :other.',
    'in_array_keys'          => 'يجب أن يحتوي حقل :attribute على الأقل على أحد المفاتيح التالية: :values.',
    'integer'                => 'يجب أن يكون حقل :attribute عددًا صحيحًا.',
    'ip'                     => 'يجب أن يكون حقل :attribute عنوان IP صحيحًا.',
    'ipv4'                   => 'يجب أن يكون حقل :attribute عنوان IPv4 صحيحًا.',
    'ipv6'                   => 'يجب أن يكون حقل :attribute عنوان IPv6 صحيحًا.',
    'json'                   => 'يجب أن يكون حقل :attribute نصًا من نوع JSON.',
    'list'                   => 'يجب أن يكون حقل :attribute قائمة.',
    'lowercase'              => 'يجب أن يحتوي الحقل :attribute على حروف صغيرة.',
    'lt'                     => [
        'array'   => 'يجب أن يحتوي حقل :attribute على أقل من :value عناصر/عنصر.',
        'file'    => 'يجب أن يكون حجم ملف حقل :attribute أصغر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أصغر من :value.',
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'string'  => 'يجب أن يكون طول نص حقل :attribute أقل من :value حروفٍ/حرفًا.',
    ],
    'lte'                    => [
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'array'   => 'يجب ألا يحتوي حقل :attribute على أكثر من :value عناصر/عنصر.',
        'file'    => 'يجب أن لا يتجاوز حجم ملف حقل :attribute :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute مساوية أو أصغر من :value.',
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'string'  => 'يجب ألا يتجاوز طول نص حقل :attribute :value حروفٍ/حرفًا.',
    ],
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'mac_address'            => 'يجب أن يكون الحقل :attribute عنوان MAC صالحًا.',
    'max'                    => [
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'array'   => 'يجب ألا يحتوي حقل :attribute على أكثر من :max عناصر/عنصر.',
        'file'    => 'يجب أن لا يتجاوز حجم ملف حقل :attribute :max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute مساوية أو أصغر من :max.',
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'string'  => 'يجب ألا يتجاوز طول نص حقل :attribute :max حروفٍ/حرفًا.',
    ],
    'max_digits'             => 'يجب ألا يحتوي الحقل :attribute على أكثر من :max رقم/أرقام.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'mimes'                  => 'يجب أن يكون :attribute ملفًا من النوع: :values.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'mimetypes'              => 'يجب أن يكون :attribute ملفًا من النوع: :values.',
    'min'                    => [
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'array'   => 'يجب أن يحتوي حقل :attribute على الأقل على :min عنصرًا/عناصر.',
        'file'    => 'يجب أن يكون حجم ملف حقل :attribute على الأقل :min كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute مساوية أو أكبر من :min.',
        'string'  => 'يجب أن يكون طول نص حقل :attribute على الأقل :min حروفٍ/حرفًا.',
    ],
    'min_digits'             => 'يجب أن يحتوي الحقل :attribute على الأقل :min رقم/أرقام.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'missing'                => 'يجب أن يكون الحقل :attribute مفقودًا.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'missing_if'             => 'يجب أن يكون الحقل :attribute مفقودًا عندما تكون قيمة :other هي :value.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'missing_unless'         => 'يجب أن يكون الحقل :attribute مفقودًا ما لم تكن قيمة :other هي :value.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'missing_with'           => 'يجب أن يكون الحقل :attribute مفقودًا عند وجود :values.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'missing_with_all'       => 'يجب أن يكون الحقل :attribute مفقودًا عند وجود :values.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'multiple_of'            => 'يجب أن يكون حقل :attribute من مضاعفات :value.',
    'not_in'                 => 'يجب ألا يكون حقل :attribute موجودًا في القائمة.',
    'not_regex'              => 'صيغة حقل :attribute غير صحيحة.',
    'numeric'                => 'يجب على حقل :attribute أن يكون رقمًا.',
    'password'               => [
        'letters'       => 'يجب أن يحتوي حقل :attribute على حرف واحد على الأقل.',
        'mixed'         => 'يجب أن يحتوي حقل :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers'       => 'يجب أن يحتوي حقل :attribute على رقمٍ واحدٍ على الأقل.',
        'symbols'       => 'يجب أن يحتوي حقل :attribute على رمزٍ واحدٍ على الأقل.',
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'uncompromised' => 'ظهر حقل :attribute في تسريب بيانات. الرجاء اختيار :attribute مختلف.',
    ],
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'present'                => 'يجب أن يكون حقل :attribute موجودًا.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'present_if'             => 'يجب أن يكون الحقل :attribute موجودًا عندما تكون قيمة :other هي :value.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'present_unless'         => 'يجب أن يكون الحقل :attribute موجودًا ما لم تكن قيمة :other هي :value.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'present_with'           => 'يجب أن يكون الحقل :attribute موجودًا عند وجود :values.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'present_with_all'       => 'يجب أن يكون الحقل :attribute موجودًا عند وجود :values.',
    'prohibited'             => 'حقل :attribute محظور.',
    'prohibited_if'          => 'حقل :attribute محظور إذا كان :other هو :value.',
    'prohibited_if_accepted' => 'حقل :attribute محظور عند قبول الحقل :other.',
    'prohibited_if_declined' => 'حقل :attribute محظور عند رفض الحقل :other.',
    'prohibited_unless'      => 'حقل :attribute محظور ما لم يكن :other ضمن :values.',
    'prohibits'              => 'الحقل :attribute يحظر تواجد الحقل :other.',
    'regex'                  => 'صيغة حقل :attribute غير صحيحة.',
    'required'               => 'حقل :attribute مطلوب.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'required_array_keys'    => 'يجب أن يحتوي الحقل :attribute على مدخلات للمفاتيح: :values.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'required_if'            => 'حقل :attribute مطلوب عندما تكون قيمة :other هي :value.',
    'required_if_accepted'   => 'الحقل :attribute مطلوب عند قبول الحقل :other.',
    'required_if_declined'   => 'الحقل :attribute مطلوب عند رفض الحقل :other.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'required_unless'        => 'حقل :attribute مطلوب ما لم تكن قيمة :other ضمن :values.',
    'required_with'          => 'حقل :attribute مطلوب إذا توفّر :values.',
    'required_with_all'      => 'حقل :attribute مطلوب إذا توفّر :values.',
    'required_without'       => 'حقل :attribute مطلوب إذا لم يتوفّر :values.',
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'required_without_all'   => 'حقل :attribute مطلوب إذا لم يتوفر أي من :values.',
    'same'                   => 'يجب أن يتطابق حقل :attribute مع :other.',
    'size'                   => [
        'array'   => 'يجب أن يحتوي حقل :attribute على :size عنصرٍ/عناصر بالضبط.',
        'file'    => 'يجب أن يكون حجم ملف حقل :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute مساوية لـ :size.',
        'string'  => 'يجب أن يحتوي نص حقل :attribute على :size حروفٍ/حرفًا بالضبط.',
    ],
    // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
    'starts_with'            => 'يجب أن يبدأ حقل :attribute بأحد القيم التالية: :values.',
    'string'                 => 'يجب أن يكون حقل :attribute نصًا.',
    'timezone'               => 'يجب أن يكون حقل :attribute نطاقًا زمنيًا صحيحًا.',
    'ulid'                   => 'حقل :attribute يجب أن يكون بصيغة ULID سليمة.',
    'unique'                 => 'قيمة حقل :attribute مُستخدمة من قبل.',
    // curated at 2026-09-22T22:56:03+07:00 — ar curation wave 3: upload vs download, product ID/UID collision
    'uploaded'               => 'فشل رفع الحقل :attribute.',
    'uppercase'              => 'يجب أن يحتوي الحقل :attribute على حروف كبيرة.',
    'url'                    => 'صيغة رابط حقل :attribute غير صحيحة.',
    'uuid'                   => 'حقل :attribute يجب أن يكون بصيغة UUID سليمة.',
    'attributes'             => [
        'address'                  => 'العنوان',
        'affiliate_url'            => 'رابط الأفلييت',
        'age'                      => 'العمر',
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'amount'                   => 'المبلغ',
        'announcement'             => 'إعلان',
        'area'                     => 'المنطقة',
        'audience_prize'           => 'جائزة الجمهور',
        'audience_winner'          => 'الفائز باختيار الجمهور',
        'available'                => 'مُتاح',
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'birthday'                 => 'تاريخ الميلاد',
        'body'                     => 'المُحتوى',
        'city'                     => 'المدينة',
        'color'                    => 'اللون',
        'company'                  => 'الشركة',
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'compilation'              => 'التجميع',
        'concept'                  => 'المفهوم',
        'conditions'               => 'الشروط',
        'content'                  => 'المُحتوى',
        'contest'                  => 'المسابقة',
        'country'                  => 'الدولة',
        'cover'                    => 'الغلاف',
        'created_at'               => 'تاريخ الإضافة',
        'creator'                  => 'المنشئ',
        'currency'                 => 'العملة',
        'current_password'         => 'كلمة المرور الحالية',
        'customer'                 => 'العميل',
        'date'                     => 'التاريخ',
        'date_of_birth'            => 'تاريخ الميلاد',
        'dates'                    => 'التواريخ',
        'day'                      => 'اليوم',
        'deleted_at'               => 'تاريخ الحذف',
        'description'              => 'الوصف',
        'display_type'             => 'نوع العرض',
        'district'                 => 'الحي',
        'duration'                 => 'المدة',
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'email'                    => 'البريد الإلكتروني',
        'excerpt'                  => 'المُلخص',
        'filter'                   => 'التصفية',
        'finished_at'              => 'تاريخ الانتهاء',
        'first_name'               => 'الاسم الأول',
        'gender'                   => 'الجنس',
        'grand_prize'              => 'الجائزة الكبرى',
        'group'                    => 'المجموعة',
        // curated at 2026-09-23T14:02:04+07:00 — missing hCaptcha attribute label
        'h-captcha-response'       => 'الكابتشا',
        'hour'                     => 'ساعة',
        'image'                    => 'صورة',
        'image_desktop'            => 'صورة سطح المكتب',
        'image_main'               => 'الصورة الرئيسية',
        'image_mobile'             => 'صورة الجوال',
        'images'                   => 'الصور',
        'is_audience_winner'       => 'الفائز باختيار الجمهور',
        'is_hidden'                => 'مخفي',
        'is_subscribed'            => 'مشترك',
        'is_visible'               => 'مرئي',
        'is_winner'                => 'الفائز',
        'items'                    => 'العناصر',
        'key'                      => 'المفتاح',
        'last_name'                => 'اسم العائلة',
        'lesson'                   => 'الدرس',
        'line_address_1'           => 'العنوان 1',
        'line_address_2'           => 'العنوان 2',
        'login'                    => 'تسجيل الدخول',
        'message'                  => 'الرسالة',
        'middle_name'              => 'الاسم الأوسط',
        'minute'                   => 'دقيقة',
        'mobile'                   => 'الجوال',
        'month'                    => 'الشهر',
        'name'                     => 'الاسم',
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'national_code'            => 'الرمز الوطني',
        'number'                   => 'الرقم',
        'password'                 => 'كلمة المرور',
        'password_confirmation'    => 'تأكيد كلمة المرور',
        'phone'                    => 'الهاتف',
        'photo'                    => 'الصورة',
        'portfolio'                => 'ملف الأعمال',
        'postal_code'              => 'الرمز البريدي',
        'preview'                  => 'معاينة',
        'price'                    => 'السعر',
        // curated at 2026-09-22T22:56:03+07:00 — ar curation wave 3: upload vs download, product ID/UID collision
        'product_id'               => 'معرّف المنتج',
        // curated at 2026-09-22T22:56:03+07:00 — ar curation wave 3: upload vs download, product ID/UID collision
        'product_uid'              => 'معرّف المنتج الفريد',
        'product_uuid'             => 'معرف المنتج',
        'promo_code'               => 'الرمز الترويجي',
        'province'                 => 'المحافظة',
        'quantity'                 => 'الكمية',
        'reason'                   => 'السبب',
        'recaptcha_response_field' => 'حقل استجابة recaptcha',
        'referee'                  => 'الحكَم',
        'referees'                 => 'الحكّام',
        'region'                   => 'المنطقة',
        'reject_reason'            => 'سبب الرفض',
        'remember'                 => 'تذكير',
        'restored_at'              => 'تاريخ الاستعادة',
        'result_text_under_image'  => 'نص النتيجة أسفل الصورة',
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'role'                     => 'الدور',
        'rule'                     => 'القاعدة',
        'rules'                    => 'القواعد',
        'second'                   => 'ثانية',
        'sex'                      => 'الجنس',
        'shipment'                 => 'الشحنة',
        'short_text'               => 'نص مختصر',
        'size'                     => 'الحجم',
        'skills'                   => 'المهارات',
        // curated at 2026-09-22T20:04:41+07:00 — ar curation wave
        'slug'                     => 'المعرّف',
        'specialization'           => 'التخصص',
        'started_at'               => 'تاريخ الابتداء',
        'state'                    => 'الولاية',
        'status'                   => 'الحالة',
        'street'                   => 'الشارع',
        'student'                  => 'الطالب',
        'subject'                  => 'الموضوع',
        'tag'                      => 'العلامة',
        'tags'                     => 'العلامات',
        'teacher'                  => 'المعلّم',
        'terms'                    => 'الأحكام',
        'test_description'         => 'وصف الاختبار',
        'test_locale'              => 'لغة الاختبار',
        'test_name'                => 'اسم الاختبار',
        'text'                     => 'النص',
        'time'                     => 'الوقت',
        // curated at 2026-09-22T20:24:52+07:00 — ar curation wave
        'title'                    => 'العنوان',
        'type'                     => 'النوع',
        'updated_at'               => 'تاريخ التحديث',
        'user'                     => 'المستخدم',
        'username'                 => 'اسم المُستخدم',
        'value'                    => 'القيمة',
        'winner'                   => 'الفائز',
        'work'                     => 'العمل',
        'year'                     => 'السنة',
    ],
];
