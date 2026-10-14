import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { syncSkill, runHook } from '../../scripts/sync-project-skill.mjs';

const name = 'shengxue360-development';
const skillDir = `docs/skills/${name}`;
function fixture(t) {
  const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'growth-skill-test-'));
  t.after(() => fs.rmSync(temp, { recursive: true, force: true }));
  const root = path.join(temp, 'repo');
  const skillHome = path.join(temp, 'skills');
  const write = (file, content) => {
    fs.mkdirSync(path.dirname(path.join(root, file)), { recursive: true });
    fs.writeFileSync(path.join(root, file), content);
  };
  write(`${skillDir}/SKILL.md`, '---\nname: shengxue360-development\ndescription: test\n---\n\n手写业务规则必须保留。\n<!-- project-state:start -->\n旧摘要\n<!-- project-state:end -->\n后续手写说明。\n');
  write(`${skillDir}/agents/openai.yaml`, 'interface:\n  display_name: "测试"\n');
  write('docs/开发设计文档.md', '# 设计\n版本：V1.0\n');
  write('app/Models/Student.php', '<?php class Student {}');
  write('resources/js/app/Student.vue', '<template>学生</template>');
  return { root, skillHome, write, options: { root, skillHome } };
}
const read = file => fs.readFileSync(file, 'utf8');

test('安装后持续反映源码编辑、删除和重命名，保留手写规则', t => {
  const f = fixture(t);
  const first = syncSkill({ ...f.options, install: true });
  assert.equal(first.target, path.join(f.skillHome, name));
  f.write('database/migrations/002_add_grade.php', '<?php // migration');
  fs.renameSync(path.join(f.root, 'app/Models/Student.php'), path.join(f.root, 'app/Models/Child.php'));
  fs.unlinkSync(path.join(f.root, 'resources/js/app/Student.vue'));
  f.write('docs/开发设计文档.md', '# 设计\n版本：V2.0\n');
  const next = syncSkill(f.options);
  assert.notEqual(next.fingerprint, first.fingerprint);
  assert.ok(next.changedFiles.includes('app/Models/Student.php'));
  const snapshot = read(path.join(f.root, skillDir, 'references/project-state.md'));
  assert.ok(snapshot.includes('app/Models/Child.php'));
  assert.ok(!snapshot.includes('app/Models/Student.php'));
  assert.ok(!snapshot.includes('resources/js/app/Student.vue'));
  const source = read(path.join(f.root, skillDir, 'SKILL.md'));
  assert.ok(source.includes('手写业务规则必须保留。'));
  assert.ok(source.endsWith('后续手写说明。\n'));
  assert.ok(source.includes('V2.0'));
  assert.equal(source, read(path.join(f.skillHome, name, 'SKILL.md')));
  assert.equal(snapshot, read(path.join(f.skillHome, name, 'references/project-state.md')));
});

test('重复同步没有反馈循环，不重写文件；手写规则编辑会进入副本', t => {
  const f = fixture(t);
  syncSkill({ ...f.options, install: true });
  const file = path.join(f.root, skillDir, 'SKILL.md');
  fs.utimesSync(file, 1000, 1000);
  const before = fs.statSync(file).mtimeMs;
  assert.equal(syncSkill(f.options).updated, false);
  assert.equal(fs.statSync(file).mtimeMs, before);
  fs.writeFileSync(file, read(file).replace('手写业务规则必须保留。', '新确认的规则。'));
  syncSkill(f.options);
  assert.ok(read(path.join(f.skillHome, name, 'SKILL.md')).includes('新确认的规则。'));
});

test('同一份代码部署到不同目录后同步不修改已生成文件', t => {
  const local = fixture(t);
  const server = fixture(t);
  const first = syncSkill({ ...local.options, install: true });
  fs.cpSync(path.join(local.root, skillDir), path.join(server.root, skillDir), { recursive: true });

  const files = ['SKILL.md', 'agents/openai.yaml', 'references/project-state.md'];
  for (const file of files) fs.utimesSync(path.join(server.root, skillDir, file), 1000, 1000);
  const deployed = syncSkill(server.options);
  assert.equal(deployed.fingerprint, first.fingerprint);
  assert.equal(deployed.target, null);
  assert.equal(deployed.updated, false);
  for (const file of files) {
    const source = read(path.join(local.root, skillDir, file));
    assert.equal(read(path.join(server.root, skillDir, file)), source);
    assert.equal(read(path.join(local.skillHome, name, file)), source);
    assert.equal(fs.statSync(path.join(server.root, skillDir, file)).mtimeMs, 1000000);
  }
  const snapshot = read(path.join(server.root, skillDir, 'references/project-state.md'));
  assert.ok(!snapshot.includes(fs.realpathSync(local.root)));
  assert.ok(!snapshot.includes(fs.realpathSync(server.root)));
  assert.equal(JSON.parse(read(path.join(local.skillHome, name, '.project-binding.json'))).root, fs.realpathSync(local.root));
  assert.equal(syncSkill(server.options).updated, false);
});

test('环境、业务备份和符号链接不进入清单或影响指纹', t => {
  const f = fixture(t);
  const before = syncSkill(f.options);
  for (const file of ['.env', '.local-access.txt', 'storage/app/private/backups/data.sql', 'app/.env.local']) f.write(file, 'SENSITIVE_TEST_SENTINEL');
  fs.symlinkSync(path.join(f.root, '.env'), path.join(f.root, 'app/linked.php'));
  const after = syncSkill(f.options);
  assert.equal(after.fingerprint, before.fingerprint);
  assert.equal(after.updated, false);
  const reference = read(path.join(f.root, skillDir, 'references/project-state.md'));
  assert.ok(!reference.includes('SENSITIVE_TEST_SENTINEL'));
  assert.ok(!reference.includes('linked.php'));
});

test('同名未绑定 skill 不覆盖，自动同步也核对项目绑定', t => {
  const f = fixture(t);
  const target = path.join(f.skillHome, name);
  fs.mkdirSync(target, { recursive: true });
  fs.writeFileSync(path.join(target, 'SKILL.md'), '其他项目');
  assert.throws(() => syncSkill({ ...f.options, install: true }), /拒绝覆盖/);
  assert.equal(read(path.join(target, 'SKILL.md')), '其他项目');
  fs.rmSync(target, { recursive: true });
  syncSkill({ ...f.options, install: true });
  fs.writeFileSync(path.join(target, '.project-binding.json'), JSON.stringify({ root: '/other/project' }));
  assert.throws(() => syncSkill(f.options), /拒绝覆盖/);
});

test('hook 输出符合事件约定，Stop 不阻止结束，错误也输出 JSON', t => {
  const f = fixture(t);
  const post = runHook({ hook_event_name: 'PostToolUse' }, f.options);
  assert.equal(post.hookSpecificOutput.hookEventName, 'PostToolUse');
  assert.equal(post.decision, undefined);
  assert.deepEqual(runHook({ hook_event_name: 'PostToolUse' }, f.options), {});
  f.write('app/Models/New.php', '<?php // new');
  assert.deepEqual(runHook({ hook_event_name: 'Stop' }, f.options), {});
  assert.deepEqual(runHook({ hook_event_name: 'PreToolUse' }, f.options), {});
  const result = spawnSync(process.execPath, [path.resolve('scripts/sync-project-skill.mjs'), '--hook', '--root', f.root, '--skill-home', f.skillHome], { input: '{invalid', encoding: 'utf8' });
  assert.equal(result.status, 1);
  assert.equal(typeof JSON.parse(result.stdout).systemMessage, 'string');
});

test('生成标记损坏时停止同步，避免覆盖手写内容', t => {
  const f = fixture(t);
  const file = path.join(f.root, skillDir, 'SKILL.md');
  fs.writeFileSync(file, '# 手写内容，没有生成标记\n');
  assert.throws(() => syncSkill(f.options), /标记/);
  assert.equal(read(file), '# 手写内容，没有生成标记\n');
});
