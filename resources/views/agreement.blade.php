<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#235e48">
    <title>用户协议与隐私政策 · 成长档案</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f6f7f2; color: #263b33; font: 16px/1.85 -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", sans-serif; overflow-wrap: anywhere; }
        main { max-width: 800px; margin: 32px auto; padding: 36px 42px; background: white; border-radius: 20px; }
        nav { margin-bottom: 24px; font-size: 14px; }
        a { color: #235e48; text-underline-offset: 4px; }
        h1 { margin: 0 0 8px; font-size: 28px; line-height: 1.5; }
        h2 { margin: 30px 0 12px; font-size: 20px; }
        p { margin: 12px 0; }
        li { margin: 8px 0; }
        ul { padding-left: 22px; }
        .date { color: #6d7d73; margin-bottom: 24px; font-size: 14px; }
        .notice { padding: 16px 18px; background: #edf3e8; border-left: 3px solid #235e48; border-radius: 8px; }
        .contact { padding: 16px 18px; background: #f6f7f2; border-radius: 12px; }
        @media (max-width: 600px) { main { margin: 0; padding: 24px 20px 36px; border-radius: 0; } h1 { font-size: 24px; } }
    </style>
</head>
<body>
<main>
    @unless($embedded)
        <nav aria-label="返回"><a href="/app/register">返回家长注册</a></nav>
    @endunless
    <h1>用户协议与隐私政策</h1>
    <p class="date">最近更新日期：{{ config('agreement.updated_at') }}</p>
    <p>欢迎使用本服务（以下简称"本服务"）。在注册前，请您务必仔细阅读并充分理解本《用户协议与隐私政策》（以下简称"本协议"）。当您在注册页面勾选"我已阅读并同意"，即视为您已理解并同意本协议的全部内容。</p>
    <p class="notice"><strong>请您特别注意：</strong>本服务涉及未成年人个人信息（学生姓名、就读学校、成绩等）。如您为孩子填报，请确保您是孩子的父母或法定监护人，或已取得监护人的授权。</p>

    <section aria-labelledby="account"><h2 id="account">一、账号注册与使用</h2>
        <ul><li>您需使用本人真实手机号码完成注册，并对账号下的一切行为负责。</li>
        <li>请妥善保管账号与密码，因密码泄露造成的损失由您自行承担。</li>
        <li>每个手机号码仅可注册一个账号，不得转让、出借或买卖账号。</li></ul>
    </section>
    <section aria-labelledby="collection"><h2 id="collection">二、我们收集的信息</h2>
        <p>为向您提供成绩记录与招生意向采集服务，我们仅收集实现功能所必需的以下信息：</p>
        <ul><li>账号信息：手机号、密码（经加密存储）、您的称呼；</li>
        <li>学生信息：学生姓名、性别、就读学校、入学年份、入学年级、联系方式；</li>
        <li>成绩与意向信息：您填报的各科成绩、等级、考试批次，以及意向学校与意向等级。</li></ul>
        <p>上述信息中，学生姓名、就读学校、成绩与意向信息属于个人信息，涉及不满14周岁未成年人时属于敏感个人信息。</p>
    </section>
    <section aria-labelledby="usage"><h2 id="usage">三、信息的使用</h2>
        <p>我们仅将您的信息用于成绩记录、统计分析与招生意向采集等本服务约定功能。</p>
        <p>在您勾选同意本协议的前提下，我们可将相关汇总信息提供给学校或招生方，用于招生咨询与录取参考。未经您的明确同意，我们不会向任何第三方提供可识别到具体个人的信息。</p>
        <p>我们不会将您的信息用于广告营销或向无关第三方出售、出租。</p>
    </section>
    <section aria-labelledby="protection"><h2 id="protection">四、信息的保护</h2>
        <p>密码采用单向加密（哈希）方式存储，任何工作人员均无法查看明文密码。</p>
        <p>服务全程采用加密传输（HTTPS），防止数据在传输过程中被窃取。</p>
        <p>我们采取访问控制、操作留痕等技术措施，确保仅授权人员可接触数据。</p>
        <p>如发生或可能发生数据泄露，我们将依照法律要求及时采取补救措施并履行告知义务。</p>
    </section>
    <section aria-labelledby="rights"><h2 id="rights">五、您的权利</h2>
        <p>查阅与更正：您可随时登录账号查看、修改或补充已填报的信息。</p>
        <p>删除：您可删除单个学生的档案、成绩或意向记录；您也可申请注销账号，我们将删除或匿名化处理您的全部个人信息。</p>
        <p>撤回同意：您可在登录后撤回授权，撤回后我们将停止向第三方提供您的信息，但不影响此前基于您的同意已进行的处理。</p>
        <p>拒绝提供：若您不提供必要信息，我们将无法为您开通本服务。</p>
    </section>
    <section aria-labelledby="children"><h2 id="children">六、未成年人保护</h2>
        <p>本服务主要面向学生家长或法定监护人。请您在填报前确认已获得必要的家庭内部授权。</p>
        <p>如您发现误填报了他人或无关人员的信息，请及时删除或联系我们处理。</p>
    </section>
    <section aria-labelledby="changes"><h2 id="changes">七、协议变更</h2>
        <p>我们可能根据法律法规或服务调整更新本协议。更新后的版本将在本页面发布并以适当方式提示您，继续使用本服务即视为接受更新后的内容。</p>
    </section>
    <section aria-labelledby="contact"><h2 id="contact">八、联系我们</h2>
        <p>如您对本协议或个人信息处理有任何疑问、意见或投诉，可通过以下方式联系我们：</p>
        <p class="contact">联系电话：<a href="tel:{{ config('agreement.contact_phone') }}">{{ config('agreement.contact_phone') }}</a></p>
    </section>
</main>
</body>
</html>
