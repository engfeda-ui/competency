# Mandatory Versioning & SSH Deployment Directive

> ⚠️ **STRICT USER DIRECTIVE**: For every single edit, update, bug fix, or feature addition made in any project or plugin in this workspace, you MUST ALWAYS automatically execute the following 4-step workflow without being asked:

---

## 1. 📋 Document Changelog & Bump Version
- Document the update in `README.md` under the `## 📋 Changelog` section with current date and version.
- Increment the version number in `version.php` and update the version badge in `README.md`.

---

## 2. 🔀 Git Push (Master Branch)
- Push changes to GitHub:
  ```bash
  git add .
  git commit -m "<type>(<scope>): <version> <description>"
  git push origin master
  ```

---

## 3. 📦 Package ZIP Artifacts
- Run the packaging PowerShell script to update ZIP files in `packaged_plugins/`:
  ```powershell
  powershell -ExecutionPolicy Bypass -File "c:\Users\mahmo\OneDrive - Energy & Water Academy\Work\Repo\package_moodle_plugins.ps1"
  ```

---

## 4. 🚀 Direct SSH Deployment to Production LMS Server (`150.230.241.37`)
- **Host:** `ubuntu@150.230.241.37`
- **SSH Key:** `C:\Users\mahmo\Documents\ssh-key-2026-07-10 (production lms).key`
- **Plugin Paths on Host:**
  - `competency-report` → `/home/ubuntu/moodle-project/local/comp_report_ext/`
  - `block_competency_report` → `/home/ubuntu/moodle-project/blocks/comp_report_ext/`
  - `competency` → `/home/ubuntu/moodle-project/question/bank/comp_ext/`
  - `failgrade` → `/home/ubuntu/moodle-project/mod/quiz/accessrule/failgrade_ext/`
  - `attemptpassword` → `/home/ubuntu/moodle-project/mod/quiz/accessrule/attemptpassword/`
- **Full PowerShell Deploy Command (run from PowerShell):**
  ```powershell
  $sshKey    = "C:\Users\mahmo\Documents\ssh-key-2026-07-10 (production lms).key"
  $remote    = "ubuntu@150.230.241.37"
  $localPath = "c:\Users\mahmo\OneDrive - Energy & Water Academy\Work\Repo\<plugin-folder>"
  $remotePath = "/home/ubuntu/moodle-project/<plugin-remote-path>/"

  cmd /c "tar -czf - -C `"$localPath`" . | ssh -i `"$sshKey`" -o StrictHostKeyChecking=no $remote `"sudo tar -xzf - -C $remotePath`""
  ssh -i $sshKey -o StrictHostKeyChecking=no $remote "sudo docker exec -u www-data moodle-app php /var/www/html/admin/cli/upgrade.php --non-interactive && sudo docker exec -u www-data moodle-app php /var/www/html/admin/cli/purge_caches.php"
  ```

---

## 🗂️ Plugin → Path Mapping Reference

| Plugin (local folder) | Production Path |
|---|---|
| `competency-report` | `/home/ubuntu/moodle-project/local/comp_report_ext/` |
| `block_competency_report` | `/home/ubuntu/moodle-project/blocks/comp_report_ext/` |
| `competency` | `/home/ubuntu/moodle-project/question/bank/comp_ext/` |
| `failgrade` | `/home/ubuntu/moodle-project/mod/quiz/accessrule/failgrade_ext/` |
| `attemptpassword` | `/home/ubuntu/moodle-project/mod/quiz/accessrule/attemptpassword/` |

## 🐳 Docker Container Name
| Environment | Container Name |
|---|---|
| Production | `moodle-app` |
