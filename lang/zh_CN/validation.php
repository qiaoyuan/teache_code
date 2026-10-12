<?php

return [
    'required' => ':attribute不能为空', 'string' => ':attribute格式不正确', 'regex' => ':attribute格式不正确', 'numeric' => ':attribute必须是数字', 'integer' => ':attribute必须是整数', 'confirmed' => '两次输入的密码不一致', 'different' => '新密码必须与当前密码不同', 'date_format' => '日期格式不正确', 'before_or_equal' => '考试日期不能晚于今天', 'decimal' => ':attribute最多保留两位小数', 'gt' => ':attribute必须大于 :value', 'in' => ':attribute选项无效', 'array' => ':attribute格式不正确', 'min' => ['numeric' => ':attribute不能小于 :min', 'string' => ':attribute至少 :min 个字符', 'array' => '至少填写 :min 个科目'], 'max' => ['numeric' => ':attribute不能大于 :max', 'string' => ':attribute不能超过 :max 个字符', 'array' => '最多填写 :max 个科目'],
    'attributes' => ['phone' => '手机号', 'password' => '密码', 'current_password' => '当前密码', 'password_confirmation' => '确认密码', 'name' => '姓名', 'school' => '学校名称', 'school_at_exam' => '考试时学校', 'title' => '考试名称', 'entry_year' => '入学年份', 'contact_phone' => '联系手机号', 'subjects' => '科目', 'subjects.*.name' => '科目名称', 'subjects.*.score' => '分数', 'subjects.*.max_score' => '满分', 'subjects.*.grade' => '等级', 'note' => '备注', 'version' => '记录版本'],
];
