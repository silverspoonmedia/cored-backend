# Security Audit Procedure (OWASP Top 10:2025)

**Version:** 1.0  
**Last Updated:** 2026-06-16  
**Audit Frequency:** Quarterly, or after major feature releases, or before production deployments  

---

## Purpose

This document provides a **repeatable, standardized procedure** for conducting defensive security audits of this Laravel application. Use this prompt when requesting security audits from AI agents or performing manual security reviews.

**When to Run This Audit:**
- Quarterly security reviews
- Before major production deployments
- After adding new features or dependencies
- After security incidents or vulnerability disclosures
- When onboarding new team members (educational)

---

## Audit Scope

**In Scope:**
- Source code review (PHP, JavaScript, Blade templates)
- Configuration files (`.env`, `config/*.php`, etc.)
- Dependency analysis (`composer.json`, `package.json`)
- Route and middleware configuration
- Authentication and authorization logic
- Database queries and ORM usage
- Input validation and output encoding

**Out of Scope:**
- Penetration testing against production systems
- Third-party service security (Pusher, S3, etc.)
- Infrastructure security (server, network, firewall)
- Social engineering or physical security
- Performance testing or DoS simulation

**Important Constraints:**
- Non-destructive testing only
- No data exfiltration
- No modification of production data
- Local/staging environment only

---

## AI Agent Prompt Template

**Copy the prompt below when requesting a security audit from an AI agent:**

---

### 🔒 SECURITY AUDIT REQUEST

You are a senior security engineer conducting a defensive security audit of a Laravel application based on **OWASP Top 10:2025**. This is a periodic review (not a one-time audit).

**Previous Audit Date:** [Check SECURITY_AUDIT.md for last audit date]  
**Current Date:** [Insert current date]  
**Audit Scope:** Source code, configuration, dependencies, local testing only  

**Your Tasks:**

1. **Review Previous Audit**
   - Read `SECURITY_AUDIT.md` to understand past findings
   - Note which findings were patched vs. still open
   - Identify if new code has reintroduced old vulnerabilities

2. **Conduct New Audit**
   - Audit against OWASP Top 10:2025 categories (A01-A10)
   - Focus on code changes since last audit date
   - Run dependency checks: `composer audit`, `npm audit`
   - Review route list: `php artisan route:list`
   - Search for security anti-patterns

3. **Compare with Previous Audit**
   - Mark findings as: NEW, RECURRING, or STILL OPEN
   - If a patched finding has regressed, flag as RECURRING
   - Document what changed since last audit

4. **Output Format**
   - Update `SECURITY_AUDIT.md` with new findings
   - Keep previous audit history in the file
   - Add a new section: `## Audit History` if not present
   - Each audit should have: date, auditor, findings count, status

5. **Patching**
   - Apply patches for CRITICAL and HIGH findings immediately
   - Document patches in the audit report
   - Run verification commands
   - Update README.md if security posture changed

**Audit Categories (OWASP Top 10:2025):**

### A01:2025 - Broken Access Control
- Routes without authentication middleware
- Missing authorization checks (Policy, Gate)
- IDOR vulnerabilities
- Mass assignment issues
- Privilege escalation paths
- Horizontal/vertical access bypass

### A02:2025 - Security Misconfiguration
- `APP_DEBUG=true` in production
- Missing security headers
- Session configuration (secure cookie, encryption)
- CORS misconfiguration
- Rate limiting on sensitive endpoints
- Error messages exposing internals

### A03:2025 - Software Supply Chain Failures
- Vulnerable dependencies (`composer audit`, `npm audit`)
- Unpinned dependencies (especially from custom VCS)
- Post-install scripts in `package.json`
- Abandoned or unmaintained packages

### A04:2025 - Cryptographic Failures
- Password hashing (must use `Hash::make` or bcrypt)
- Weak password policies
- Plaintext storage of secrets
- Missing HTTPS enforcement
- JWT secret strength
- Token expiry and revocation

### A05:2025 - Injection
- Raw SQL queries (`DB::raw`, `whereRaw`, etc.)
- Command injection (shell execution)
- XSS (Blade `{!! !!}` usage)
- Path traversal in file operations
- LDAP, XML, or template injection

### A06:2025 - Insecure Design
- Business logic flaws
- Race conditions (stok, saldo, concurrent updates)
- Missing idempotency (payment, webhook)
- State machine bypasses
- Insufficient server-side validation

### A07:2025 - Authentication Failures
- Missing rate limiting on login/register
- Weak password policies
- Session fixation
- Insecure password reset flow
- Missing MFA or account lockout
- Token/session management issues

### A08:2025 - Software or Data Integrity Failures
- File upload validation (MIME, size, location)
- Unsigned webhook payloads
- Missing signed URLs for sensitive actions
- Deserialization of untrusted data
- Debug endpoints in production

### A09:2025 - Security Logging and Alerting Failures
- Missing audit logs (login, logout, privilege changes)
- Logging sensitive data (passwords, tokens)
- No alerting for suspicious activity
- Insufficient exception logging

### A10:2025 - Mishandling of Exceptional Conditions
- Fail-open error handling
- Exception swallowing
- Missing transaction rollback
- Timeout/resource cleanup issues
- Retry logic causing side effects

**Commands to Run:**

```bash
# Dependency check
composer audit
npm audit

# Route analysis
php artisan route:list --path=api
php artisan route:list

# Search for raw SQL
rg "DB::raw|whereRaw|orderByRaw|selectRaw" --type php

# Search for potential XSS
rg "\{!!" resources/views/

# Check environment
php artisan env
```

**Deliverables:**

1. Updated `SECURITY_AUDIT.md` with:
   - New audit date and auditor
   - Summary of new/recurring/resolved findings
   - Detailed findings with severity and patch status
   - Verification steps for patches
   - Comparison with previous audit

2. Applied patches for CRITICAL and HIGH findings

3. Updated `README.md` if production checklist changed

4. Brief summary with:
   - Total findings by severity
   - Count of new vs recurring findings
   - Patched vs open findings
   - Risk assessment

**Important Notes:**

- Do NOT run destructive tests
- Do NOT commit secrets or sensitive data
- Do NOT modify production systems
- Do NOT expose secrets in audit report (use `[REDACTED]`)
- Always follow the CHUNKED WRITE PROTOCOL for file operations

---

## Audit History Template

Add this section to `SECURITY_AUDIT.md` if not present:

```markdown
## Audit History

### Audit #2 - [Date]
**Auditor:** [Name/AI Agent]  
**Findings:** X new, Y recurring, Z resolved  
**Critical:** X  
**High:** X  
**Medium:** X  
**Low:** X  
**Status:** [Brief summary]

### Audit #1 - 2026-06-16
**Auditor:** Security Engineering Team  
**Findings:** 20 total (2 critical, 2 high, 5 medium, 2 low, 9 info)  
**Critical:** 2 (both patched)  
**High:** 2 (both patched)  
**Medium:** 5 (4 patched, 1 requires external coordination)  
**Low:** 2 (accepted risk)  
**Status:** 19/20 resolved. Production-ready with deploy checklist.
```

---

## Post-Audit Actions

After completing the audit:

1. **Review Findings**
   - Triage by severity
   - Assess exploitability and impact
   - Prioritize patching

2. **Apply Patches**
   - CRITICAL: Patch immediately
   - HIGH: Patch within 1 week
   - MEDIUM: Patch within 1 month
   - LOW: Patch opportunistically or accept risk

3. **Verify Patches**
   - Run verification commands
   - Test functionality not broken
   - Update tests if needed

4. **Update Documentation**
   - Update README.md with security changes
   - Update deployment checklist if needed
   - Document any new security controls

5. **Track Progress**
   - Create tickets for open findings
   - Set deadlines for patch implementation
   - Schedule follow-up audit if needed

---

## References

- **OWASP Top 10:2025:** https://owasp.org/www-project-top-ten/
- **Laravel Security Best Practices:** https://laravel.com/docs/security
- **Previous Audit Report:** `SECURITY_AUDIT.md`
- **Production Checklist:** `README.md` Security section

---

## Notes for AI Agents

When performing this audit:

1. **Always read `SECURITY_AUDIT.md` first** to understand previous findings
2. **Compare current code** with previous audit date to identify changes
3. **Flag regressions** if patched findings reappear
4. **Document methodology** for each finding (how it was discovered)
5. **Provide proof-of-concept** (safe, non-destructive) for vulnerabilities
6. **Include verification steps** for all patches applied
7. **Follow CHUNKED WRITE PROTOCOL** when creating/editing files
8. **Never expose secrets** in the audit report

---

**End of Security Audit Procedure**
