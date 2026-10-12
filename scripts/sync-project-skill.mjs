#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const name = 'shengxue360-development';
const sourceDir = `docs/skills/${name}`;
const start = '<!-- project-state:start -->';
const end = '<!-- project-state:end -->';
const roots = ['app', 'bootstrap', 'config', 'database/migrations', 'database/seeders', 'resources', 'routes', 'lang', 'tests', 'scripts', 'deploy'];
const explicit = ['AGENTS.md', 'README.md', '.npmrc', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'phpunit.xml', 'tsconfig.json', 'vite.config.js', '.codex/hooks.json', 'docs/开发设计文档.md', 'docs/AI开发指令.md', `${sourceDir}/SKILL.md`, `${sourceDir}/agents/openai.yaml`];
const extensions = new Set(['.php', '.vue', '.ts', '.js', '.mjs', '.css', '.scss', '.json', '.yaml', '.yml', '.toml', '.sh', '.conf', '.md', '.xml', '.sql', '.txt']);
const hash = (data) => crypto.createHash('sha256').update(data).digest('hex');
const read = (file) => fs.readFileSync(file, 'utf8');
const json = (file, fallback = {}) => fs.existsSync(file) ? JSON.parse(read(file)) : fallback;
const escapeCell = (value) => String(value).replaceAll('|', '\\|').replaceAll('\n', ' ');

function atomicWrite(file, content) {
  if (fs.existsSync(file) && read(file) === content) return false;
  fs.mkdirSync(path.dirname(file), { recursive: true });
  const temp = `${file}.${process.pid}.${crypto.randomBytes(4).toString('hex')}.tmp`;
  try {
    fs.writeFileSync(temp, content, { mode: 0o600 });
    fs.renameSync(temp, file);
  } finally {
    if (fs.existsSync(temp)) fs.unlinkSync(temp);
  }
  return true;
}

function splitSkill(content) {
  const first = content.indexOf(start);
  const last = content.indexOf(end);
  if (first < 0 || last < first || content.indexOf(start, first + start.length) !== -1 || content.indexOf(end, last + end.length) !== -1) {
    throw new Error('SKILL.md 必须包含唯一且有序的 project-state 标记');
  }
  return [content.slice(0, first + start.length), content.slice(last)];
}

function collectFiles(root) {
  const files = new Set();
  function walk(relative) {
    const absolute = path.join(root, relative);
    if (!fs.existsSync(absolute)) return;
    const stat = fs.lstatSync(absolute);
    if (stat.isSymbolicLink()) return;
    if (stat.isDirectory()) {
      for (const child of fs.readdirSync(absolute).sort()) {
        if (child.startsWith('.') || ['cache', 'node_modules', 'vendor', 'backups', 'logs', 'credentials', 'secrets'].includes(child)) continue;
        walk(`${relative}/${child}`);
      }
    } else if (stat.isFile() && (extensions.has(path.extname(relative)) || ['scripts/node', 'scripts/php'].includes(relative))) {
      files.add(relative);
    }
  }
  for (const directory of roots) walk(directory);
  for (const relative of explicit) {
    const absolute = path.join(root, relative);
    if (fs.existsSync(absolute) && fs.lstatSync(absolute).isFile() && !fs.lstatSync(absolute).isSymbolicLink()) files.add(relative);
  }
  return [...files].sort();
}

function acquireLock(file) {
  for (let attempt = 0; attempt < 100; attempt++) {
    try {
      const fd = fs.openSync(file, 'wx', 0o600);
      fs.writeFileSync(fd, String(process.pid));
      fs.closeSync(fd);
      return () => { if (fs.existsSync(file)) fs.unlinkSync(file); };
    } catch (error) {
      if (error.code !== 'EEXIST') throw error;
      try {
        const pid = Number(read(file));
        if (Number.isSafeInteger(pid) && pid > 0) {
          try { process.kill(pid, 0); } catch (error) {
            if (error.code === 'ESRCH') fs.unlinkSync(file);
          }
        } else if (Date.now() - fs.statSync(file).mtimeMs > 10000) {
          fs.unlinkSync(file);
        }
      } catch (error) { if (error.code !== 'ENOENT') throw error; }
      Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 20);
    }
  }
  throw new Error('另一个同步进程尚未结束，请稍后重试');
}

function validateTarget(target, root) {
  if (target === path.join(root, sourceDir)) throw new Error('安装目录不能是仓库源目录');
  if (!fs.existsSync(target)) return;
  if (fs.lstatSync(target).isSymbolicLink()) throw new Error('安装目录不能是符号链接');
  const binding = json(path.join(target, '.project-binding.json'), null);
  if (!binding || binding.root !== root) throw new Error('已有同名 skill 未绑定当前项目，拒绝覆盖');
}

function snapshot(root, files, fingerprint) {
  const design = fs.existsSync(path.join(root, 'docs/开发设计文档.md')) ? read(path.join(root, 'docs/开发设计文档.md')) : '';
  const version = design.match(/版本[：:]\s*([^\r\n]+)/)?.[1] || '未标记';
  const npmLock = json(path.join(root, 'package-lock.json'));
  const composerLock = json(path.join(root, 'composer.lock'));
  const composer = json(path.join(root, 'composer.json'));
  const versions = [
    ['PHP 要求', composer.require?.php || '未声明'],
    ['Laravel', composerLock.packages?.find(p => p.name === 'laravel/framework')?.version || '未锁定'],
    ...['vue', 'typescript', 'vite', 'element-plus'].map(p => [p, npmLock.packages?.[`node_modules/${p}`]?.version || '未锁定']),
  ];
  const sections = [
    ['模型', 'app/Models/'], ['控制器与请求校验', 'app/Http/Controllers/', 'app/Http/Requests/'],
    ['服务、授权与中间件', 'app/Services/', 'app/Policies/', 'app/Http/Middleware/'],
    ['数据库迁移与初始化', 'database/migrations/', 'database/seeders/'],
    ['前端与模板', 'resources/'], ['测试', 'tests/'], ['运行、部署与同步', 'scripts/', 'deploy/'],
  ];
  const routeFile = path.join(root, 'routes/web.php');
  const routeLines = fs.existsSync(routeFile) ? read(routeFile).split('\n').filter(line => /Route::(?:get|post|put|patch|delete|prefix|middleware|pattern)\(|foreach\s*\(/.test(line)).map(line => line.trim()) : [];
  const lines = [
    '# 项目结构快照（自动生成）', '', `项目目录：\`${root}\``, `设计版本：${version}`, `源文件指纹：\`${fingerprint}\``, '',
    '来源是当前磁盘文件，不代表测试通过、需求确认或部署完成。不读取数据库、环境凭据或聊天。', '',
    '开发依据：项目根目录的 `docs/开发设计文档.md`、`docs/AI开发指令.md` 和 `README.md`。', '',
    '## 锁定依赖', '', '| 依赖 | 版本 |', '| --- | --- |',
    ...versions.map(([p, v]) => `| ${escapeCell(p)} | ${escapeCell(v)} |`), '',
    '## 页面和接口声明', '',
    '以下直接提取 `routes/web.php` 的声明，需结合 prefix、middleware 和 foreach 理解完整路径；不是运行时 route:list。', '',
    '```php', ...routeLines, '```', '',
  ];
  for (const [title, ...prefixes] of sections) {
    lines.push(`## ${title}`, '', ...files.filter(file => prefixes.some(prefix => file.startsWith(prefix))).map(file => `- \`${file}\``), '');
  }
  return { text: `${lines.join('\n')}\n`, version };
}

export function syncSkill({ root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..'), install = false, skillHome = path.join(process.env.CODEX_HOME || path.join(os.homedir(), '.codex'), 'skills') } = {}) {
  root = fs.realpathSync(root);
  const privateDir = path.join(root, 'storage/app/private/skill-sync');
  fs.mkdirSync(privateDir, { recursive: true });
  const release = acquireLock(path.join(privateDir, 'sync.lock'));
  try {
    const skillFile = path.join(root, sourceDir, 'SKILL.md');
    const [before, after] = splitSkill(read(skillFile));
    const files = collectFiles(root);
    const manifest = Object.fromEntries(files.map(file => [file, hash(file === `${sourceDir}/SKILL.md` ? `${before}\n${after}` : fs.readFileSync(path.join(root, file)))]));
    const fingerprint = hash(JSON.stringify(manifest));
    const previous = json(path.join(privateDir, 'state.json'));
    const changedFiles = [...new Set([...Object.keys(previous.manifest || {}), ...files])].filter(file => previous.manifest?.[file] !== manifest[file]);
    const migrationCount = files.filter(file => file.startsWith('database/migrations/')).length;
    const generated = snapshot(root, files, fingerprint);
    const summary = `\n\n当前设计 ${generated.version}；${migrationCount} 个迁移；${files.length} 个受监测源文件。源指纹：\`${fingerprint.slice(0, 16)}\`。\n详见 [项目结构快照](references/project-state.md)。本段由 hook 更新。\n\n`;
    const contents = {
      'SKILL.md': `${before}${summary}${after}`,
      'agents/openai.yaml': read(path.join(root, sourceDir, 'agents/openai.yaml')),
      'references/project-state.md': generated.text,
    };
    const registration = path.join(privateDir, 'installation.json');
    const target = install ? path.resolve(skillHome, name) : json(registration, null)?.target;
    if (target) validateTarget(target, root);
    let updated = false;
    for (const [relative, content] of Object.entries(contents)) {
      updated = atomicWrite(path.join(root, sourceDir, relative), content) || updated;
      if (target) updated = atomicWrite(path.join(target, relative), content) || updated;
    }
    if (target) {
      atomicWrite(path.join(target, '.project-binding.json'), `${JSON.stringify({ root }, null, 2)}\n`);
      if (install) atomicWrite(registration, `${JSON.stringify({ target }, null, 2)}\n`);
    }
    atomicWrite(path.join(privateDir, 'state.json'), `${JSON.stringify({ fingerprint, manifest }, null, 2)}\n`);
    return { updated, changedFiles, fingerprint, target: target || null };
  } finally { release(); }
}

export function runHook(payload, options = {}) {
  if (!['PostToolUse', 'Stop'].includes(payload.hook_event_name)) return {};
  const result = syncSkill(options);
  return payload.hook_event_name === 'PostToolUse' && result.updated ? {
    hookSpecificOutput: {
      hookEventName: 'PostToolUse',
      additionalContext: '升学360 skill 的项目结构已同步。若本次改变业务约定，请同时更新开发设计文档、AI开发指令和 skill 手写规则。',
    },
  } : {};
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const args = process.argv.slice(2);
  const hook = args.includes('--hook');
  const option = flag => args.includes(flag) ? args[args.indexOf(flag) + 1] : undefined;
  try {
    const options = { install: args.includes('--install'), root: option('--root'), skillHome: option('--skill-home') };
    if (hook) {
      const payload = JSON.parse(fs.readFileSync(0, 'utf8'));
      process.stdout.write(`${JSON.stringify(runHook(payload, options))}\n`);
    } else {
      const result = syncSkill(options);
      process.stdout.write(`skill ${result.updated ? '已更新' : '已是最新'}；源文件变化 ${result.changedFiles.length} 项${result.target ? '；安装副本已同步' : ''}。\n`);
    }
  } catch (error) {
    if (hook) process.stdout.write(`${JSON.stringify({ systemMessage: '项目 skill 同步失败，请手动运行 npm run skill:sync 查看原因。' })}\n`);
    else process.stderr.write(`skill 同步失败：${error.message}\n`);
    process.exitCode = 1;
  }
}
