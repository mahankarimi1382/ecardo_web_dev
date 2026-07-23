# 🔷 ECardo — Enterprise Architecture Audit & Redesign Roadmap

> **Document Type:** Enterprise Architecture Audit, As-Is Analysis, To-Be Design & Migration Roadmap  
> **Platform:** ECardo — International FinTech Platform  
> **Date:** 2026-07-23  
> **Version:** 1.0  
> **Classification:** Confidential — Internal Architecture Team  
> **Scope:** Full-stack architecture audit, financial services platform redesign, multi-currency wallet, ledger, exchange, settlement, compliance, and future services readiness

---

## فهرست مطالب

1. [مقدمه و خلاصه اجرایی](#1-مقدمه-و-خلاصه-اجرایی)
2. [مرحله اول: بررسی معماری فعلی (As-Is)](#2-مرحله-اول-بررسی-معماری-فعلی-as-is)
3. [مرحله دوم: تحلیل نقاط ضعف](#3-مرحله-دوم-تحلیل-نقاط-ضعف)
4. [مرحله سوم: معماری ماژولار پیشنهادی](#4-مرحله-سوم-معماری-ماژولار-پیشنهادی)
5. [مرحله چهارم: بازطراحی کیف پول](#5-مرحله-چهارم-بازطراحی-کیف-پول)
6. [مرحله پنجم: بازطراحی Ledger](#6-مرحله-پنجم-بازطراحی-ledger)
7. [مرحله ششم: طراحی Exchange Engine](#7-مرحله-ششم-طراحی-exchange-engine)
8. [مرحله هفتم: طراحی Settlement Engine](#8-مرحله-هفتم-طراحی-settlement-engine)
9. [مرحله هشتم: ممیزی امنیت](#9-مرحله-هشتم-ممیزی-امنیت)
10. [مرحله نهم: تحلیل ریسک مسدودی دارایی‌ها](#10-مرحله-نهم-تحلیل-ریسک-مسدودی-داراییها)
11. [مرحله دهم: سرویس‌های مالی آینده](#11-مرحله-دهم-سرویسهای-مالی-آینده)
12. [مرحله یازدهم: تحلیل ماژول‌ها](#12-مرحله-یازدهم-تحلیل-ماژولها)
13. [Enterprise Architecture Roadmap نهایی](#13-enterprise-architecture-roadmap-نهایی)

---

# 1. مقدمه و خلاصه اجرایی

## 1.1 شرح پروژه

ECardo یک پلتفرم مالی بین‌المللی با هدف ارائه خدمات زیر است:

- **کیف پول چندارزی** (فیات و رمزارز)
- **صرافی آنلاین** (فیات⇄فیات، فیات⇄کریپتو، کریپتو⇄کریپتو)
- **حواله بین‌المللی**
- **کارت مجازی و فیزیکی**
- **پرداخت QR و P2P**
- **خدمات مسافرتی** (eSIM، هتل، بلیط، بیمه)
- **سرویس‌های تکمیلی** (Escrow، Subscription، Cashback، Loyalty)

## 1.2 وضعیت فعلی

**متأسفانه، Repository فعلی کاملاً خالی است.** تنها فایل موجود `main.md` است که محتوایی ندارد. هیچ کد Backend، Frontend، Mobile، پیکربندی، یا معماری تعریف‌شده‌ای وجود ندارد. این به این معنی است:

- ✅ بدهی فنی (Technical Debt) = **صفر**
- ✅ بدهی معماری (Architectural Debt) = **صفر**
- ✅ وابستگی‌های قدیمی = **صفر**
- ❌ هیچ زیرساختی = **نیاز به طراحی از صفر**
- ❌ هیچ تجربه عملیاتی = **نیاز به تصمیم‌گیری‌های معماری کلیدی**

**این یک فرصت استثنایی است** — می‌توان معماری را از ابتدا با اصول Enterprise، Clean Architecture، DDD، CQRS و Event-Driven طراحی کرد بدون نیاز به مهاجرت از سیستم قدیمی.

## 1.3 چشم‌انداز معماری

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                        ECARDO PLATFORM ECOSYSTEM                             │
│                                                                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ │
│  │ Mobile Apps  │  │  Web App    │  │ Admin Panel │  │  Partner APIs       │ │
│  │ iOS/Android  │  │  Next.js    │  │  Dashboard  │  │  B2B Integration    │ │
│  └──────┬───────┘  └──────┬──────┘  └──────┬──────┘  └──────────┬──────────┘ │
│         │                 │                │                    │            │
│  ═══════╪═════════════════╪════════════════╪════════════════════╪════════    │
│         │           API GATEWAY (Kong/Apigee)                  │            │
│  ═══════╪═════════════════╪════════════════╪════════════════════╪════════    │
│         │                 │                │                    │            │
│  ┌──────┴─────────────────┴────────────────┴────────────────────┴───────┐    │
│  │                    SERVICE MESH (Istio/Linkerd)                      │    │
│  │                                                                      │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │    │
│  │  │ Identity │ │  Wallet  │ │  Ledger  │ │ Exchange │ │ Payment  │  │    │
│  │  │ Service  │ │  Service │ │  Service │ │  Engine  │ │ Gateway  │  │    │
│  │  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘  │    │
│  │                                                                      │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │    │
│  │  │    KYC   │ │   AML    │ │   Risk   │ │Settlement│ │  Card    │  │    │
│  │  │  Service │ │  Engine  │ │  Engine  │ │  Engine  │ │  Service │  │    │
│  │  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘  │    │
│  │                                                                      │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │    │
│  │  │ Notif.   │ │  Audit   │ │ Merchant │ │   FX     │ │Remittance│  │    │
│  │  │ Service  │ │  Service │ │  Service │ │  Engine  │ │  Service │  │    │
│  │  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘  │    │
│  │                                                                      │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │    │
│  │  │  Travel  │ │  eSIM    │ │  QR Pay  │ │ Escrow   │ │ Loyalty  │  │    │
│  │  │  Service │ │  Service │ │  Service │ │  Service │ │  Service │  │    │
│  │  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘  │    │
│  └──────────────────────────────────────────────────────────────────────┘    │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐    │
│  │                     DATA & EVENT LAYER                               │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │    │
│  │  │ Kafka /  │ │ Redis    │ │PostgreSQL│ │ MongoDB  │ │ Elastic  │  │    │
│  │  │ RabbitMQ │ │ Cluster  │ │ Cluster  │ │ Cluster  │ │ Search   │  │    │
│  │  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘  │    │
│  └──────────────────────────────────────────────────────────────────────┘    │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐    │
│  │                  INFRASTRUCTURE & OBSERVABILITY                      │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │    │
│  │  │  K8s     │ │  Vault   │ │  Grafana  │ │ Jaeger  │ │  Terraform│  │    │
│  │  │ Cluster  │ │  (Secrets)│ │Prometheus│ │ Tracing │ │  IaC     │  │    │
│  │  └──────────┘ └──────────┘ └──────────┘ └──────────┘ └──────────┘  │    │
│  └──────────────────────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

# 2. مرحله اول: بررسی معماری فعلی (As-Is)

## 2.1 خلاصه وضعیت فعلی

| لایه/مؤلفه | وضعیت فعلی | ارزیابی |
|---|---|---|
| **Backend** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Frontend** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Mobile** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **API** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Database** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Queue** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Cache** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Authentication** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Wallet** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Ledger** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Payment** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Exchange** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Settlement** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Notification** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Admin** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Reporting** | ❌ وجود ندارد | نیاز به طراحی از صفر |
| **Logging** | ❌ وجود ندارد | نیاز به طراحی از صفر |

## 2.2 تصمیمات معماری کلیدی که باید اتخاذ شود

### 2.2.1 انتخاب زبان و فریمورک

**توصیه:** استفاده از **پلی‌گلات** (Polyglot) — زبان‌های مختلف برای سرویس‌های مختلف بر اساس نقاط قوت هر زبان:

| سرویس | زبان پیشنهادی | دلیل |
|---|---|---|
| Core Services (Wallet, Ledger, Exchange) | **Go** یا **Java (Spring Boot)** | Performance بالا، Concurrency عالی، Ecosystem بالغ |
| API Gateway & BFF | **Node.js (NestJS)** یا **Go** | Async I/O، سرعت توسعه بالا |
| AML/KYC/Risk Engine | **Python (FastAPI)** | ML/AI libraries، Rule Engine flexibility |
| Frontend Web | **Next.js (React)** | SSR/SSG، SEO، Ecosystem قوی |
| Mobile | **Flutter** یا **React Native** | Cross-platform، سرعت توسعه |
| Admin Panel | **Next.js** یا **Refine.dev** | سرعت ساخت Panel مدیریتی |
| Event Processing | **Go** یا **Kafka Streams** | Throughput بالا |

**توصیه اصلی برای شروع سریع:**

اگر تیم کوچک است و سرعت توسعه اولویت دارد: **Node.js (NestJS) + TypeScript** برای تمام Backend services  
اگر Performance و مقیاس‌پذیری اولویت دارد: **Go** برای Core services + **Python** برای AML/Risk

### 2.2.2 انتخاب معماری

**توصیه:** Modular Monolith در فاز اول → Microservices در فاز دوم

```
فاز ۱ (ماه ۱-۶):  Modular Monolith
├── یک Deployment Unit
├── ماژول‌های داخلی کاملاً جدا
├── Event-Driven communication بین ماژول‌ها
├── Database per Module (یا Schema per Module)
└── آماده برای تبدیل به Microservice

فاز ۲ (ماه ۷-۱۲): Extract Microservices
├── جدا کردن ماژول‌های بحرانی به Microservice مستقل
├── API Gateway + Service Mesh
├── Event Bus مستقل (Kafka)
└── Independent Deployment per Service

فاز ۳ (ماه ۱۳+): Full Microservices
├── تمام سرویس‌ها مستقل
├── Auto-scaling
├── Multi-region
└── Event Sourcing + CQRS برای سرویس‌های مالی
```

**دلیل این تصمیم:**
- شروع با Microservices برای تیم کوچک = complexity بی‌مورد
- Modular Monolith امکان جداسازی تمیز را فراهم می‌کند
- مهاجرت تدریجی به Microservices بدون بازنویسی
- هر ماژول از ابتدا با Interface مشخص طراحی می‌شود

### 2.2.3 انتخاب زیرساخت

| مؤلفه | توصیه |
|---|---|
| **Container Orchestration** | Kubernetes (EKS/GKE/AKS) |
| **CI/CD** | GitHub Actions + ArgoCD |
| **IaC** | Terraform + Helm Charts |
| **Secret Management** | HashiCorp Vault |
| **Service Mesh** | Istio یا Linkerd |
| **API Gateway** | Kong یا AWS API Gateway |
| **Message Broker** | Apache Kafka (primary) + RabbitMQ (secondary) |
| **Cache** | Redis Cluster (Sentinel/Cluster mode) |
| **Primary Database** | PostgreSQL 16+ (ACID compliance for financial data) |
| **Document Store** | MongoDB (for flexible schemas — KYC docs, audit trails) |
| **Search** | Elasticsearch (audit logs, transaction search) |
| **Time Series** | TimescaleDB یا InfluxDB (market data, rate history) |
| **Object Storage** | MinIO (self-hosted) یا S3 (documents, media) |

### 2.2.4 Cloud Strategy

**توصیه: Multi-Cloud / Hybrid Cloud**

```
 PRIMARY CLOUD                    SECONDARY CLOUD
┌──────────────────┐             ┌──────────────────┐
│   AWS یا GCP     │             │  Self-Hosted DC   │
│                  │◄───────────►│  (برای داده‌های    │
│ • K8s Cluster    │  Replication│   حساس و سازگاری  │
│ • Managed DB     │             │   با مقررات محلی)  │
│ • Object Storage │             │                    │
│ • CDN            │             │ • K8s Cluster      │
│ • Monitoring     │             │ • PostgreSQL       │
└──────────────────┘             │ • Vault            │
                                 └──────────────────┘
```

**دلیل:**
- برخی داده‌های مالی ممکن است نیاز به نگهداری در دیتاسنتر محلی داشته باشند
- کاهش وابستگی به یک Cloud Provider
- Disaster Recovery بین دو منطقه

---

# 3. مرحله دوم: تحلیل نقاط ضعف

## 3.1 ماتریس ارزیابی وضعیت فعلی

از آنجا که پروژه در مرحله صفر است، تحلیل نقاط ضعف بر اساس **ریسک‌های طراحی** و **دام‌های رایج** در پلتفرم‌های مالی مشابه انجام می‌شود.

### 3.1.1 امنیت (Security)

| بُعد | ریسک | اولویت |
|---|---|---|
| Authentication | عدم استفاده از MFA از ابتدا | 🔴 بحرانی |
| Authorization | عدم تفکیک RBAC/ABAC | 🔴 بحرانی |
| Data Encryption | رمزنگاری ناکافی در Transit و Rest | 🔴 بحرانی |
| API Security | عدم Rate Limiting و DDoS Protection | 🔴 بحرانی |
| Secret Management | Hardcoded credentials در کد | 🔴 بحرانی |
| Wallet Security | عدم Multi-Signature برای تراکنش‌های بالا | 🔴 بحرانی |
| Session Management | Session Hijacking | 🟡 مهم |
| Input Validation | SQL Injection, XSS | 🟡 مهم |
| Audit Trail | عدم ثبت کامل عملیات | 🔴 بحرانی |
| Compliance | عدم رعایت PCI-DSS, GDPR | 🔴 بحرانی |

### 3.1.2 Performance

| بُعد | ریسک | اولویت |
|---|---|---|
| Database | بدون Indexing Strategy | 🟡 مهم |
| Cache | بدون Caching Strategy | 🟡 مهم |
| Connection Pooling | عدم مدیریت Connection‌ها | 🟡 مهم |
| N+1 Queries | مشکل رایج در ORMها | 🟠 متوسط |
| Blocking Operations | عدم استفاده از Async Processing | 🟡 مهم |
| Rate Limiting | عدم محدودیت نرخ درخواست | 🔴 بحرانی |
| Payload Size | عدم فشرده‌سازی پاسخ‌ها | 🟠 متوسط |

### 3.1.3 Scalability

| بُعد | ریسک | اولویت |
|---|---|---|
| Horizontal Scaling | عدم طراحی Stateless | 🔴 بحرانی |
| Database Scaling | عدم Partitioning/Sharding Strategy | 🟡 مهم |
| Event Processing | عدم Async Event Processing | 🟡 مهم |
| File Storage | عدم Object Storage Strategy | 🟠 متوسط |
| CDN | عدم استفاده از CDN | 🟠 متوسط |
| Load Balancing | عدم Load Balancer Strategy | 🟡 مهم |

### 3.1.4 Maintainability

| بُعد | ریسک | اولویت |
|---|---|---|
| Code Structure | عدم Clean Architecture | 🔴 بحرانی |
| Dependency Management | عدم Dependency Injection | 🟡 مهم |
| Testing | عدم Unit/Integration/E2E Test Strategy | 🔴 بحرانی |
| Documentation | عدم API Documentation (OpenAPI) | 🟡 مهم |
| Code Review | عدم PR Review Process | 🟡 مهم |
| Versioning | عدم Semantic Versioning | 🟠 متوسط |
| Error Handling | عدم Centralized Error Handling | 🟡 مهم |

### 3.1.5 Availability & Fault Tolerance

| بُعد | ریسک | اولویت |
|---|---|---|
| Health Checks | عدم Liveness/Readiness Probes | 🔴 بحرانی |
| Circuit Breaker | عدم Circuit Breaker Pattern | 🔴 بحرانی |
| Retry Policy | عدم Retry with Exponential Backoff | 🟡 مهم |
| Timeout | عدم Timeout Configuration | 🟡 مهم |
| Bulkhead | عدم Bulkhead Pattern | 🟡 مهم |
| Graceful Degradation | عدم Fallback Strategy | 🟡 مهم |
| Rate Limiting | عدم Rate Limiter | 🔴 بحرانی |

### 3.1.6 Disaster Recovery

| بُعد | ریسک | اولویت |
|---|---|---|
| Backup Strategy | عدم Automated Backup | 🔴 بحرانی |
| RPO/RTO | عدم تعریف Recovery Point/Time Objective | 🔴 بحرانی |
| Multi-Region | عدم Multi-Region Deployment | 🟡 مهم |
| Failover | عدم Automatic Failover | 🔴 بحرانی |
| Data Replication | عدم Cross-Region Replication | 🟡 مهم |
| Runbook | عدم Incident Response Playbook | 🟡 مهم |

### 3.1.7 Monitoring & Logging

| بُعد | ریسک | اولویت |
|---|---|---|
| Centralized Logging | عدم ELK/Loki Stack | 🔴 بحرانی |
| Metrics | عدم Prometheus/Grafana | 🔴 بحرانی |
| Distributed Tracing | عدم Jaeger/Zipkin | 🟡 مهم |
| Alerting | عدم Alert Manager | 🔴 بحرانی |
| Dashboard | عدم Operational Dashboard | 🟡 مهم |
| Log Retention | عدم Log Retention Policy | 🟡 مهم |
| Structured Logging | عدم JSON Structured Logs | 🟠 متوسط |

### 3.1.8 Architectural Principles

| اصل | وضعیت | اولویت |
|---|---|---|
| **Clean Architecture** | ❌ پیاده‌سازی نشده | 🔴 بحرانی |
| **SOLID** | ❌ پیاده‌سازی نشده | 🔴 بحرانی |
| **DDD** | ❌ پیاده‌سازی نشده | 🔴 بحرانی |
| **CQRS** | ❌ پیاده‌سازی نشده (برای سرویس‌های مالی حیاتی است) | 🔴 بحرانی |
| **Event Sourcing** | ❌ پیاده‌سازی نشده (برای Ledger حیاتی است) | 🔴 بحرанی |
| **Event-Driven** | ❌ پیاده‌سازی نشده | 🔴 بحرانی |
| **Microservice Readiness** | ❌ آماده نیست | 🟡 مهم |
| **12-Factor App** | ❌ پیاده‌سازی نشده | 🔴 بحرانی |
| **Domain Events** | ❌ پیاده‌سازی نشده | 🔴 بحرانی |
| **Saga Pattern** | ❌ پیاده‌سازی نشده (برای تراکنش‌های توزیع‌شده) | 🔴 بحرانی |

---

# 4. مرحله سوم: معماری ماژولار پیشنهادی

## 4.1 نقشه سرویس‌ها (Service Map)

### 4.1.1 سرویس‌های هسته (Core Services) — اولویت بحرانی

```
┌─────────────────────────────────────────────────────────────────┐
│                     CORE SERVICES                                │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 1. IDENTITY SERVICE (AuthN + AuthZ)                       │   │
│  │    ├── Registration / Login                                │   │
│  │    ├── MFA (TOTP, SMS, Email, Biometric)                   │   │
│  │    ├── OAuth2 / OpenID Connect                             │   │
│  │    ├── RBAC + ABAC                                         │   │
│  │    ├── Session Management                                  │   │
│  │    ├── Device Management & Fingerprinting                  │   │
│  │    ├── API Key Management (for Partners)                   │   │
│  │    └── JWT + Refresh Token Rotation                        │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 2. USER SERVICE                                           │   │
│  │    ├── User Profile Management                             │   │
│  │    ├── KYC Level Management (Level 0/1/2/3)                │   │
│  │    ├── Document Storage (ID, Passport, Selfie)             │   │
│  │    ├── Address Management                                  │   │
│  │    ├── Preferences & Settings                              │   │
│  │    └── User Limits (based on KYC Level)                    │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 3. WALLET SERVICE                                         │   │
│  │    ├── Fiat Wallet (Multi-Currency)                        │   │
│  │    │   ├── IRR Wallet + Sub-wallets (Main, Gift, ...)     │   │
│  │    │   ├── USD Wallet                                      │   │
│  │    │   ├── EUR Wallet                                      │   │
│  │    │   ├── CNY Wallet                                      │   │
│  │    │   ├── TRY Wallet                                      │   │
│  │    │   ├── RUB Wallet                                      │   │
│  │    │   ├── AED Wallet                                      │   │
│  │    │   └── Dynamic Wallet (any new currency)               │   │
│  │    ├── Crypto Wallet (Multi-Chain)                         │   │
│  │    │   ├── USDT TRC20 (Tron)                               │   │
│  │    │   ├── USDT ERC20 (Ethereum)                           │   │
│  │    │   ├── USDT BEP20 (BSC)                                │   │
│  │    │   ├── BTC (Bitcoin)                                   │   │
│  │    │   ├── ETH (Ethereum)                                  │   │
│  │    │   ├── TON (TON Blockchain)                            │   │
│  │    │   └── Dynamic Chain (any new chain)                   │   │
│  │    ├── Wallet Operations (Deposit, Withdraw, Transfer)     │   │
│  │    ├── Wallet Limits & Controls                            │   │
│  │    └── Internal Transfer (P2P)                             │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 4. LEDGER SERVICE                                         │   │
│  │    ├── Double-Entry Bookkeeping                            │   │
│  │    ├── Journal Entries (all operations)                    │   │
│  │    ├── Chart of Accounts                                   │   │
│  │    ├── Account Balances (computed, never stored directly)  │   │
│  │    ├── Transaction History & Audit Trail                   │   │
│  │    ├── Reconciliation Engine                               │   │
│  │    ├── Multi-Currency Ledger                               │   │
│  │    └── Event Sourcing (immutable event log)                │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  └──────────────────────────────────────────────────────────┘   │
│  │ 5. PAYMENT GATEWAY SERVICE                                │   │
│  │    ├── PSP Integration (Multiple Providers)                │   │
│  │    ├── Card Payment Processing                             │   │
│  │    ├── Bank Transfer Processing                            │   │
│  │    ├── Crypto Payment Processing                           │   │
│  │    ├── QR Code Payment                                     │   │
│  │    ├── Payment Routing (Smart Routing)                     │   │
│  │    ├── Refund Engine                                       │   │
│  │    └── Webhook Management                                  │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### 4.1.2 سرویس‌های مالی (Financial Services) — اولویت بالا

```
┌─────────────────────────────────────────────────────────────────┐
│                   FINANCIAL SERVICES                              │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 6. FX ENGINE                                              │   │
│  │    ├── Real-time Rate Fetching (Multiple Sources)          │   │
│  │    ├── Rate Aggregation & Best Rate Selection              │   │
│  │    ├── Spread Configuration per Currency Pair              │   │
│  │    ├── Fee Calculation (Tiered, Percentage, Fixed)         │   │
│  │    ├── Rate Locking (for user-initiated conversions)       │   │
│  │    ├── Rate History & Analytics                            │   │
│  │    └── Currency Pair Management                            │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 7. EXCHANGE ENGINE                                        │   │
│  │    ├── Fiat ⇄ Fiat Exchange                               │   │
│  │    ├── Fiat ⇄ Crypto Exchange                              │   │
│  │    ├── Crypto ⇄ Fiat Exchange                              │   │
│  │    ├── Crypto ⇄ Crypto Exchange                            │   │
│  │    ├── Order Book (Market, Limit, Stop)                    │   │
│  │    ├── Matching Engine                                     │   │
│  │    ├── Slippage Protection                                 │   │
│  │    ├── Fee Engine (Maker/Taker)                            │   │
│  │    └── Exchange Rate History & Audit                       │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 8. SETTLEMENT ENGINE                                      │   │
│  │    ├── User Wallet Settlement                              │   │
│  │    ├── Bank Account Settlement                             │   │
│  │    ├── Partner Settlement                                  │   │
│  │    ├── PSP Settlement                                      │   │
│  │    ├── Exchange Settlement                                 │   │
│  │    ├── Service Provider Settlement                         │   │
│  │    ├── Instant Settlement                                 │   │
│  │    ├── Scheduled Settlement (Daily/Weekly/Monthly)         │   │
│  │    ├── Settlement Reconciliation                           │   │
│  │    └── Settlement Reports                                  │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 9. REMITTANCE SERVICE                                     │   │
│  │    ├── International Money Transfer                        │   │
│  │    ├── Corridor Management (Country Pairs)                 │   │
│  │    ├── Partner Routing (Multiple Providers)                │   │
│  │    ├── Fee & FX Calculation                                │   │
│  │    ├── Delivery Speed Options                              │   │
│  │    ├── Tracking & Status Updates                           │   │
│  │    └── Compliance Checks per Corridor                      │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### 4.1.3 سرویس‌های انطباق و ریسک (Compliance & Risk) — اولویت بحرانی

```
┌─────────────────────────────────────────────────────────────────┐
│                 COMPLIANCE & RISK SERVICES                        │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 10. KYC SERVICE                                           │   │
│  │     ├── Identity Verification (ID Card, Passport)          │   │
│  │     ├── Biometric Verification (Selfie Match)              │   │
│  │     ├── Address Verification                               │   │
│  │     ├── Document OCR & Validation                          │   │
│  │     ├── Third-Party KYC Provider Integration               │   │
│  │     │   (Jumio, Onfido, SumSub, Shufti Pro)               │   │
│  │     ├── KYC Level Management                               │   │
│  │     ├── Re-verification Triggers                           │   │
│  │     └── Sanction & PEP Screening                           │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 11. AML ENGINE                                            │   │
│  │     ├── Transaction Monitoring (Real-time)                 │   │
│  │     ├── Suspicious Activity Detection                      │   │
│  │     ├── Rule-Based Alert System                            │   │
│  │     ├── ML-Based Anomaly Detection                         │   │
│  │     ├── Case Management                                    │   │
│  │     ├── SAR (Suspicious Activity Report) Generation        │   │
│  │     ├── CTR (Currency Transaction Report)                  │   │
│  │     ├── Watchlist Screening (OFAC, EU, UN)                 │   │
│  │     ├── Risk Scoring                                       │   │
│  │     └── Regulatory Reporting                               │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 12. KYT ENGINE (Know Your Transaction)                    │   │
│  │     ├── Crypto Transaction Analysis                        │   │
│  │     ├── Blockchain Address Risk Scoring                    │   │
│  │     ├── Chain Analysis Integration (Chainalysis, Elliptic) │   │
│  │     ├── Mixer/Tumbler Detection                            │   │
│  │     ├── Dark Web Monitoring                                │   │
│  │     └── Travel Rule Compliance (FATF)                      │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 13. RISK ENGINE                                           │   │
│  │     ├── User Risk Scoring (Dynamic)                        │   │
│  │     ├── Transaction Risk Assessment                        │   │
│  │     ├── Velocity Checks                                    │   │
│  │     ├── Device Risk Analysis                               │   │
│  │     ├── Geo-Risk Analysis                                  │   │
│  │     ├── Behavioral Analysis                                │   │
│  │     ├── Fraud Detection (ML-based)                         │   │
│  │     ├── Chargeback Prevention                              │   │
│  │     └── Risk-Based Authentication Triggers                 │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 14. COMPLIANCE SERVICE                                    │   │
│  │     ├── Regulatory Rule Engine                             │   │
│  │     ├── Jurisdiction Management                            │   │
│  │     ├── Transaction Limits by Region/User Level            │   │
│  │     ├── Tax Reporting                                      │   │
│  │     ├── GDPR Data Management (Export, Delete, Anonymize)   │   │
│  │     ├── License Management                                 │   │
│  │     └── Compliance Calendar & Deadlines                    │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### 4.1.4 سرویس‌های عملیاتی (Operational Services)

```
┌─────────────────────────────────────────────────────────────────┐
│                 OPERATIONAL SERVICES                              │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 15. NOTIFICATION SERVICE                                  │   │
│  │     ├── Push Notification (FCM/APNs)                       │   │
│  │     ├── SMS (Multiple Providers: Twilio, Vonage, Local)    │   │
│  │     ├── Email (SES, SendGrid)                              │   │
│  │     ├── In-App Notification                                │   │
│  │     ├── WebSocket Real-time Updates                        │   │
│  │     ├── Notification Preferences                           │   │
│  │     ├── Template Management                                │   │
│  │     ├── Multi-language Support                             │   │
│  │     └── Rate Limiting & Throttling                         │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 16. AUDIT SERVICE                                         │   │
│  │     ├── Immutable Audit Log                                │   │
│  │     ├── Who/What/When/Where/How Recording                  │   │
│  │     ├── Data Change Tracking (Before/After)                │   │
│  │     ├── API Access Logging                                 │   │
│  │     ├── Admin Action Logging                               │   │
│  │     ├── Compliance Audit Trail                             │   │
│  │     ├── Tamper Detection                                   │   │
│  │     └── Audit Report Generation                            │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 17. MERCHANT SERVICE                                      │   │
│  │     ├── Merchant Onboarding                                │   │
│  │     ├── Merchant KYC/KYB                                   │   │
│  │     ├── Payment Link Generation                            │   │
│  │     ├── Invoice Management                                 │   │
│  │     ├── Settlement Configuration                           │   │
│  │     ├── Fee Structure Management                           │   │
│  │     ├── Merchant Dashboard                                 │   │
│  │     └── API Integration (REST + Webhook)                   │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 18. CARD SERVICE                                          │   │
│  │     ├── Virtual Card Issuance                              │   │
│  │     ├── Physical Card Management                           │   │
│  │     ├── Card Lifecycle (Issue, Activate, Block, Close)     │   │
│  │     ├── Spending Limits                                    │   │
│  │     ├── Card-to-Wallet Linking                             │   │
│  │     ├── Transaction Authorization                          │   │
│  │     ├── Card BIN Management                                │   │
│  │     └── Card Issuer Integration (Marqeta, Stripe Issuing)  │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 19. TRAVEL SERVICE                                        │   │
│  │     ├── Hotel Booking                                      │   │
│  │     ├── Flight Booking                                     │   │
│  │     ├── Travel Insurance                                   │   │
│  │     ├── Airport Services                                   │   │
│  │     └── Travel Package Management                          │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 20. eSIM SERVICE                                          │   │
│  │     ├── eSIM Purchase & Activation                         │   │
│  │     ├── Plan Management                                    │   │
│  │     ├── Usage Monitoring                                   │   │
│  │     └── Provider Integration (Airalo, Holafly)             │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 21. QR PAYMENT SERVICE                                    │   │
│  │     ├── QR Code Generation (Static/Dynamic)                │   │
│  │     ├── QR Code Scanning                                   │   │
│  │     ├── Merchant QR Payments                               │   │
│  │     ├── P2P QR Payments                                    │   │
│  │     ├── Alipay Integration                                 │   │
│  │     ├── WeChat Pay Integration                             │   │
│  │     ├── UnionPay Integration                               │   │
│  │     └── EMVCo QR Standard                                  │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 22. ESCROW SERVICE                                        │   │
│  │     ├── Escrow Account Management                          │   │
│  │     ├── Milestone-Based Release                            │   │
│  │     ├── Dispute Resolution                                 │   │
│  │     ├── Multi-Party Escrow                                 │   │
│  │     └── Auto-Release Rules                                 │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 23. LOYALTY & CASHBACK SERVICE                            │   │
│  │     ├── Points Engine                                      │   │
│  │     ├── Cashback Calculation                               │   │
│  │     ├── Tier Management                                    │   │
│  │     ├── Reward Catalog                                     │   │
│  │     ├── Referral Program                                   │   │
│  │     └── Campaign Management                                │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 24. SUBSCRIPTION SERVICE                                  │   │
│  │     ├── Plan Management                                    │   │
│  │     ├── Recurring Billing                                  │   │
│  │     ├── Invoice Generation                                 │   │
│  │     ├── Dunning Management                                 │   │
│  │     └── Subscription Analytics                             │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### 4.1.5 سرویس‌های زیرساختی (Infrastructure Services)

```
┌─────────────────────────────────────────────────────────────────┐
│                INFRASTRUCTURE SERVICES                            │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 25. API GATEWAY                                           │   │
│  │     ├── Request Routing                                    │   │
│  │     ├── Authentication & Authorization                     │   │
│  │     ├── Rate Limiting (Per User, Per IP, Per API Key)      │   │
│  │     ├── Request/Response Transformation                    │   │
│  │     ├── API Versioning                                     │   │
│  │     ├── Load Balancing                                     │   │
│  │     ├── Circuit Breaking                                   │   │
│  │     ├── Caching (Edge Caching)                             │   │
│  │     ├── Request Validation                                 │   │
│  │     ├── CORS Management                                    │   │
│  │     ├── API Analytics                                      │   │
│  │     └── Developer Portal (API Documentation)               │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 26. CONFIG SERVICE                                        │   │
│  │     ├── Centralized Configuration Management               │   │
│  │     ├── Feature Flags (LaunchDarkly / Unleash)             │   │
│  │     ├── Dynamic Configuration Updates                      │   │
│  │     └── Environment Management                             │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 27. SCHEDULER SERVICE                                     │   │
│  │     ├── Cron Job Management                                │   │
│  │     ├── Scheduled Settlements                              │   │
│  │     ├── Rate Updates                                       │   │
│  │     ├── Report Generation                                  │   │
│  │     └── Cleanup Jobs                                       │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ 28. FILE SERVICE                                          │   │
│  │     ├── Document Upload/Download                           │   │
│  │     ├── Image Processing & Optimization                    │   │
│  │     ├── Virus Scanning                                     │   │
│  │     └── CDN Integration                                    │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## 4.2 Communication Patterns

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    COMMUNICATION PATTERNS                                │
│                                                                          │
│  SYNCHRONOUS (Request/Response):                                         │
│  ┌──────────┐    HTTP/gRPC     ┌──────────┐                             │
│  │ Service A│ ───────────────► │ Service B│   Used for:                  │
│  └──────────┘                  └──────────┘   • Real-time queries        │
│                                               • User-facing operations   │
│                                               • Low-latency requirements │
│                                                                          │
│  ASYNCHRONOUS (Event-Driven):                                            │
│  ┌──────────┐    Event      ┌─────────┐    Event     ┌──────────┐      │
│  │ Service A│ ────────────► │  Kafka  │ ───────────► │ Service B│      │
│  └──────────┘               └─────────┘              └──────────┘      │
│  (Publisher)                 (Broker)               (Consumer)          │
│                                                                          │
│  Used for:                                                               │
│  • Cross-service transactions                                            │
│  • Audit logging                                                         │
│  • Notification triggers                                                 │
│  • Ledger entries                                                        │
│  • Settlement processing                                                 │
│  • AML monitoring                                                        │
│                                                                          │
│  SAGA PATTERN (Distributed Transactions):                                │
│  ┌──────────┐  Step 1  ┌──────────┐  Step 2  ┌──────────┐              │
│  │ Orchestr.│ ───────► │ Wallet   │ ───────► │ Ledger   │              │
│  │ (Saga)   │          │ Service  │          │ Service  │              │
│  └──────────┘          └──────────┘          └──────────┘              │
│       │                     │                     │                      │
│       │   Compensation      │   Compensation      │                      │
│       │ ◄────────────────── │ ◄────────────────── │                      │
│                                                                          │
│  Used for:                                                               │
│  • Deposit/Withdrawal flows                                              │
│  • Exchange operations                                                   │
│  • Cross-wallet transfers                                                │
│  • Settlement processing                                                 │
└─────────────────────────────────────────────────────────────────────────┘
```

## 4.3 Domain-Driven Design (DDD) — Bounded Contexts

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    BOUNDED CONTEXTS MAP                                   │
│                                                                          │
│  ┌─────────────────┐         ┌─────────────────┐                       │
│  │   IDENTITY      │         │    USER          │                       │
│  │   CONTEXT       │◄───────►│    CONTEXT       │                       │
│  │                 │  Shared  │                  │                       │
│  │ • User Auth     │  User ID │ • Profile        │                       │
│  │ • Sessions      │         │ • KYC             │                       │
│  │ • Permissions   │         │ • Documents       │                       │
│  └────────┬────────┘         └────────┬─────────┘                       │
│           │                           │                                  │
│           ▼                           ▼                                  │
│  ┌─────────────────┐         ┌─────────────────┐                       │
│  │   WALLET        │         │   LEDGER         │                       │
│  │   CONTEXT       │────────►│   CONTEXT        │                       │
│  │                 │ Events  │                  │                       │
│  │ • Fiat Wallets  │         │ • Journal Entries│                       │
│  │ • Crypto Wallets│         │ • Balances       │                       │
│  │ • Operations    │         │ • Accounts       │                       │
│  └────────┬────────┘         └──────────────────┘                       │
│           │                                                             │
│           ▼                                                             │
│  ┌─────────────────┐         ┌─────────────────┐                       │
│  │   EXCHANGE      │         │   PAYMENT        │                       │
│  │   CONTEXT       │────────►│   CONTEXT        │                       │
│  │                 │ Events  │                  │                       │
│  │ • Rate Engine   │         │ • PSP Gateway    │                       │
│  │ • Order Book    │         │ • Processing     │                       │
│  │ • Matching      │         │ • Refunds        │                       │
│  └────────┬────────┘         └──────────────────┘                       │
│           │                                                             │
│           ▼                                                             │
│  ┌─────────────────┐         ┌─────────────────┐                       │
│  │   SETTLEMENT    │         │   COMPLIANCE     │                       │
│  │   CONTEXT       │◄───────►│   CONTEXT        │                       │
│  │                 │ Events  │                  │                       │
│  │ • Settlements   │         │ • AML            │                       │
│  │ • Reconciliation│         │ • KYT            │                       │
│  │ • Partner Payout│         │ • Risk Scoring   │                       │
│  └─────────────────┘         └──────────────────┘                       │
│                                                                          │
│  ┌─────────────────┐         ┌─────────────────┐                       │
│  │   NOTIFICATION  │         │   AUDIT          │                       │
│  │   CONTEXT       │         │   CONTEXT        │                       │
│  │                 │         │                  │                       │
│  │ • Push/SMS/Email│         │ • Immutable Logs │                       │
│  │ • Templates     │         │ • Change Tracking│                       │
│  │ • Preferences   │         │ • Reports        │                       │
│  └─────────────────┘         └──────────────────┘                       │
└─────────────────────────────────────────────────────────────────────────┘
```

---

# 5. مرحله چهارم: بازطراحی کیف پول

## 5.1 معماری Wallet — اصل عدم تغییر هسته

### 5.1.1 ساختار داده Wallet

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    WALLET ARCHITECTURE                                   │
│                                                                          │
│  یک کاربر می‌تواند چندین Wallet داشته باشد:                             │
│                                                                          │
│  User (user_id: "usr_abc123")                                           │
│  │                                                                       │
│  ├── Fiat Wallets                                                        │
│  │   ├── IRR Wallet (wallet_id: "wlt_irr_001")                          │
│  │   │   ├── Main Sub-wallet (balance: computed from Ledger)            │
│  │   │   ├── Gift Sub-wallet                                            │
│  │   │   └── Cashback Sub-wallet                                        │
│  │   ├── USD Wallet (wallet_id: "wlt_usd_001")                          │
│  │   ├── EUR Wallet (wallet_id: "wlt_eur_001")                          │
│  │   ├── CNY Wallet (wallet_id: "wlt_cny_001")                          │
│  │   ├── TRY Wallet (wallet_id: "wlt_try_001")                          │
│  │   ├── RUB Wallet (wallet_id: "wlt_rub_001")                          │
│  │   └── AED Wallet (wallet_id: "wlt_aed_001")                          │
│  │                                                                       │
│  └── Crypto Wallets                                                      │
│      ├── USDT-TRC20 Wallet (wallet_id: "wlt_usdt_trc_001")             │
│      │   ├── Deposit Address: T...xxxx                                  │
│      │   └── HD Derivation Path: m/44'/195'/0'/0/0                      │
│      ├── USDT-ERC20 Wallet (wallet_id: "wlt_usdt_erc_001")             │
│      │   ├── Deposit Address: 0x...xxxx                                 │
│      │   └── HD Derivation Path: m/44'/60'/0'/0/0                       │
│      ├── USDT-BEP20 Wallet (wallet_id: "wlt_usdt_bep_001")             │
│      │   ├── Deposit Address: 0x...xxxx (BSC)                           │
│      │   └── HD Derivation Path: m/44'/60'/0'/0/0                       │
│      ├── BTC Wallet (wallet_id: "wlt_btc_001")                          │
│      │   ├── Deposit Address: bc1...xxxx                                │
│      │   └── HD Derivation Path: m/84'/0'/0'/0/0                        │
│      ├── ETH Wallet (wallet_id: "wlt_eth_001")                          │
│      │   ├── Deposit Address: 0x...xxxx                                 │
│      │   └── HD Derivation Path: m/44'/60'/0'/0/0                       │
│      └── TON Wallet (wallet_id: "wlt_ton_001")                          │
│          ├── Deposit Address: EQ...xxxx                                 │
│          └── HD Derivation Path: m/44'/607'/0'/0/0                      │
└─────────────────────────────────────────────────────────────────────────┘
```

### 5.1.2 Currency & Network Registry (Plugin Architecture)

**کلید طراحی: افزودن ارز یا شبکه جدید بدون تغییر هسته سیستم**

```
┌─────────────────────────────────────────────────────────────────────────┐
│                CURRENCY REGISTRY (Plugin-Based)                          │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  Currency Definition (JSON/YAML in Database)                     │    │
│  │                                                                  │    │
│  │  {                                                               │    │
│  │    "code": "USDT",                                               │    │
│  │    "name": "Tether",                                             │    │
│  │    "type": "CRYPTO",        // FIAT | CRYPTO                     │    │
│  │    "decimal_places": 6,                                          │    │
│  │    "networks": [                                                  │    │
│  │      {                                                           │    │
│  │        "network": "TRC20",                                       │    │
│  │        "chain": "TRON",                                          │    │
│  │        "contract_address": "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t",│    │
│  │        "explorer_url": "https://tronscan.org",                   │    │
│  │        "min_confirmations": 20,                                  │    │
│  │        "withdrawal_fee": 1.0,                                    │    │
│  │        "min_withdrawal": 10.0,                                   │    │
│  │        "max_withdrawal": 100000.0,                               │    │
│  │        "is_active": true,                                        │    │
│  │        "address_format": "^T[a-zA-Z0-9]{33}$",                   │    │
│  │        "handler_class": "TronNetworkHandler"                     │    │
│  │      },                                                          │    │
│  │      {                                                           │    │
│  │        "network": "ERC20",                                       │    │
│  │        "chain": "ETHEREUM",                                      │    │
│  │        "contract_address": "0xdAC17F958D2ee523a2206206994597C...",│    │
│  │        "explorer_url": "https://etherscan.io",                   │    │
│  │        "min_confirmations": 12,                                  │    │
│  │        "withdrawal_fee": 3.0,                                    │    │
│  │        "min_withdrawal": 10.0,                                   │    │
│  │        "max_withdrawal": 100000.0,                               │    │
│  │        "is_active": true,                                        │    │
│  │        "address_format": "^0x[a-fA-F0-9]{40}$",                  │    │
│  │        "handler_class": "EthereumNetworkHandler"                 │    │
│  │      }                                                           │    │
│  │    ],                                                            │    │
│  │    "rate_sources": ["binance", "coingecko", "coinmarketcap"],    │    │
│  │    "is_active": true                                             │    │
│  │  }                                                               │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  Network Handler Interface (Strategy Pattern)                    │    │
│  │                                                                  │    │
│  │  interface NetworkHandler {                                      │    │
│  │    generateAddress(userId, derivationPath): Address              │    │
│  │    getBalance(address): Balance                                  │    │
│  │    estimateFee(amount, destination): FeeEstimate                 │    │
│  │    broadcastTransaction(signedTx): TxHash                        │    │
│  │    getTransactionStatus(txHash): TxStatus                        │    │
│  │    validateAddress(address): boolean                             │    │
│  │    getConfirmations(txHash): number                              │    │
│  │    subscribeToDeposits(address, callback): Subscription          │    │
│  │  }                                                               │    │
│  │                                                                  │    │
│  │  Implementations:                                                │    │
│  │  ├── TronNetworkHandler (TRC20, TRX)                             │    │
│  │  ├── EthereumNetworkHandler (ERC20, ETH)                         │    │
│  │  ├── BscNetworkHandler (BEP20, BNB)                              │    │
│  │  ├── BitcoinNetworkHandler (BTC)                                 │    │
│  │  ├── TonNetworkHandler (TON, JETTON)                             │    │
│  │  ├── SolanaNetworkHandler (SPL, SOL)  ← آینده                    │    │
│  │  └── GenericNetworkHandler (برای شبکه‌های جدید)                   │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  Adding New Currency (Admin Flow):                               │    │
│  │                                                                  │    │
│  │  1. Admin registers new currency via Admin Panel                 │    │
│  │  2. Defines networks, fees, limits                               │    │
│  │  3. Registers Network Handler plugin                             │    │
│  │  4. System automatically:                                        │    │
│  │     • Creates wallet template                                    │    │
│  │     • Adds to FX rate sources                                    │    │
│  │     • Creates Ledger accounts                                    │    │
│  │     • Enables in user wallet creation                            │    │
│  │  5. No code changes in Core Wallet Service                      │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
```

### 5.1.3 Fiat Wallet — Detailed Design

```
┌─────────────────────────────────────────────────────────────────────────┐
│                   FIAT WALLET DETAILED DESIGN                            │
│                                                                          │
│  ┌───────────────────────────────────────────────────────────────┐      │
│  │  wallet_fiat Table                                             │      │
│  │  ├── id (UUID, PK)                                            │      │
│  │  ├── user_id (UUID, FK → users)                               │      │
│  │  ├── currency_code (VARCHAR(3)) — ISO 4217: IRR, USD, EUR...  │      │
│  │  ├── wallet_type (ENUM: MAIN, GIFT, CASHBACK, HOLD)           │      │
│  │  ├── status (ENUM: ACTIVE, FROZEN, CLOSED)                    │      │
│  │  ├── daily_limit (DECIMAL)                                    │      │
│  │  ├── monthly_limit (DECIMAL)                                  │      │
│  │  ├── per_transaction_limit (DECIMAL)                           │      │
│  │  ├── created_at (TIMESTAMP)                                   │      │
│  │  ├── updated_at (TIMESTAMP)                                   │      │
│  │  └── metadata (JSONB) — فیلدهای اضافی انعطاف‌پذیر             │      │
│  │                                                                │      │
│  │  ⚠️  هیچ فیلد balance در این جدول وجود ندارد!                 │      │
│  │  ⚠️  موجودی همیشه از Ledger محاسبه می‌شود.                    │      │
│  └───────────────────────────────────────────────────────────────┘      │
│                                                                          │
│  ┌───────────────────────────────────────────────────────────────┐      │
│  │  Fiat Currency Registry Table                                  │      │
│  │  ├── code (VARCHAR(3), PK) — ISO 4217                         │      │
│  │  ├── name (VARCHAR)                                           │      │
│  │  ├── symbol (VARCHAR) — $, €, ¥, ₺, ₽, د.إ, تومان            │      │
│  │  ├── decimal_places (INT) — IRR: 0, USD: 2, BTC: 8           │      │
│  │  ├── is_active (BOOLEAN)                                      │      │
│  │  ├── deposit_methods (JSONB) — ["bank_transfer", "card", ...] │      │
│  │  ├── withdrawal_methods (JSONB)                               │      │
│  │  ├── deposit_fee_config (JSONB)                               │      │
│  │  ├── withdrawal_fee_config (JSONB)                            │      │
│  │  ├── deposit_limits (JSONB) — per KYC level                   │      │
│  │  ├── withdrawal_limits (JSONB) — per KYC level                │      │
│  │  └── metadata (JSONB)                                         │      │
│  └───────────────────────────────────────────────────────────────┘      │
│                                                                          │
│  افزودن ارز فیات جدید:                                                  │
│  1. درج رکورد در Fiat Currency Registry                                │
│  2. تنظیم deposit/withdrawal methods                                   │
│  3. تنظیم limits و fees                                                 │
│  4. اتصال PSP/Bank برای deposit/withdrawal                             │
│  5. ایجاد حساب‌های Ledger مربوطه                                       │
│  → بدون هیچ تغییری در کد Wallet Service                               │
└─────────────────────────────────────────────────────────────────────────┘
```

### 5.1.4 Crypto Wallet — Detailed Design

```
┌─────────────────────────────────────────────────────────────────────────┐
│                  CRYPTO WALLET DETAILED DESIGN                           │
│                                                                          │
│  ┌───────────────────────────────────────────────────────────────┐      │
│  │  wallet_crypto Table                                           │      │
│  │  ├── id (UUID, PK)                                            │      │
│  │  ├── user_id (UUID, FK → users)                               │      │
│  │  ├── currency_code (VARCHAR) — USDT, BTC, ETH, TON            │      │
│  │  ├── network_code (VARCHAR) — TRC20, ERC20, BEP20, BTC, TON   │      │
│  │  ├── deposit_address (VARCHAR) — blockchain address           │      │
│  │  ├── address_index (INT) — HD wallet derivation index         │      │
│  │  ├── derivation_path (VARCHAR)                                 │      │
│  │  ├── status (ENUM: ACTIVE, FROZEN, CLOSED)                    │      │
│  │  ├── created_at (TIMESTAMP)                                   │      │
│  │  └── metadata (JSONB)                                         │      │
│  │                                                                │      │
│  │  ⚠️  هیچ فیلد balance در این جدول وجود ندارد!                 │      │
│  │  ⚠️  موجودی از Ledger + Blockchain Confirmation محاسبه می‌شود. │      │
│  └───────────────────────────────────────────────────────────────┘      │
│                                                                          │
│  ┌───────────────────────────────────────────────────────────────┐      │
│  │  Network & Token Registry Table                                │      │
│  │  ├── code (PK) — "TRC20", "ERC20", "BTC", "TON"              │      │
│  │  ├── chain_name (VARCHAR) — "TRON", "ETHEREUM", "BITCOIN"     │      │
│  │  ├── chain_type (ENUM: UTXO, ACCOUNT, OTHER)                  │      │
│  │  ├── native_token (VARCHAR) — "TRX", "ETH", "BTC", "TON"     │      │
│  │  ├── explorer_api_url (VARCHAR)                                │      │
│  │  ├── rpc_endpoint (VARCHAR) — encrypted                       │      │
│  │  ├── ws_endpoint (VARCHAR) — for real-time monitoring          │      │
│  │  ├── min_confirmations (INT)                                   │      │
│  │  ├── block_time_seconds (INT)                                  │      │
│  │  ├── hd_coin_type (INT) — BIP44 coin type                     │      │
│  │  ├── address_regex (VARCHAR) — validation pattern              │      │
│  │  ├── handler_plugin_id (VARCHAR)                               │      │
│  │  └── is_active (BOOLEAN)                                      │      │
│  │                                                                │      │
│  │  Token Registry (for tokens like USDT on different chains)     │      │
│  │  ├── id (PK)                                                   │      │
│  │  ├── network_code (FK → network_registry)                     │      │
│  │  ├── token_symbol — "USDT"                                     │      │
│  │  ├── contract_address (VARCHAR)                                │      │
│  │  ├── decimals (INT) — 6 for USDT, 18 for DAI                  │      │
│  │  ├── token_type — "TRC20", "ERC20", "BEP20"                   │      │
│  │  ├── withdrawal_fee (DECIMAL)                                  │      │
│  │  ├── min_withdrawal (DECIMAL)                                  │      │
│  │  ├── max_withdrawal (DECIMAL)                                  │      │
│  │  └── is_active (BOOLEAN)                                      │      │
│  └───────────────────────────────────────────────────────────────┘      │
│                                                                          │
│  ┌───────────────────────────────────────────────────────────────┐      │
│  │  Hot/Warm/Cold Wallet Distribution                             │      │
│  │                                                                │      │
│  │  ┌─────────┐     ┌─────────┐     ┌─────────┐                 │      │
│  │  │Hot Wallet│     │Warm     │     │Cold     │                 │      │
│  │  │(~5%)    │────►│Wallet   │────►│Wallet   │                 │      │
│  │  │         │     │(~20%)   │     │(~75%)   │                 │      │
│  │  │Online   │     │Semi-    │     │Offline  │                 │      │
│  │  │Multi-Sig│     │Online   │     │HSM/Multi│                 │      │
│  │  │2-of-3   │     │Multi-Sig│     │Sig      │                 │      │
│  │  └─────────┘     │3-of-5   │     │4-of-7   │                 │      │
│  │                   └─────────┘     └─────────┘                 │      │
│  │                                                                │      │
│  │  کاربران → Hot Wallet (واریز/برداشت سریع)                     │      │
│  │  Hot → Warm (انتقال خودکار وقتی موجودی Hot بالا رفت)         │      │
│  │  Warm → Cold (انتقال دوره‌ای برای امنیت)                      │      │
│  │  Cold → Warm → Hot (درخواست برداشت‌های بزرگ)                   │      │
│  └───────────────────────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────────────────┘
```

---

# 6. مرحله پنجم: بازطراحی Ledger

## 6.1 اصول Double-Entry Ledger

**قوانین آهنین:**
1. **هیچ موجودی مستقیماً تغییر نمی‌کند** — موجودی فقط از جمع Journal Entries محاسبه می‌شود
2. **هر عملیات = حداقل یک Journal Entry** (معمولاً 2+ Entry)
3. **مجموع بدهکار = مجموع بستانکار** (هر لحظه، برای هر Journal)
4. **Immutable** — هیچ رکوردی حذف یا ویرایش نمی‌شود
5. **Append-Only** — فقط افزودن رکورد جدید

## 6.2 ساختار Chart of Accounts

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    CHART OF ACCOUNTS                                      │
│                                                                          │
│  Level 0: Account Type                                                   │
│  ├── ASSET (دارایی)                                                     │
│  │   ├── Cash & Equivalents                                             │
│  │   │   ├── Hot Wallet - USDT TRC20                                    │
│  │   │   ├── Hot Wallet - USDT ERC20                                    │
│  │   │   ├── Hot Wallet - BTC                                           │
│  │   │   ├── Hot Wallet - ETH                                           │
│  │   │   ├── Bank Account - IRR - Mellat                                │
│  │   │   ├── Bank Account - IRR - Saman                                 │
│  │   │   ├── PSP Account - IRR - ZarinPal                               │
│  │   │   ├── Exchange Account - Binance                                 │
│  │   │   └── Exchange Account - OKX                                     │
│  │   ├── Receivables                                                     │
│  │   │   ├── PSP Receivable                                             │
│  │   │   ├── Partner Receivable                                         │
│  │   │   └── User Receivable (Negative Balance Users)                   │
│  │   └── Settlement Receivable                                          │
│  │                                                                      │
│  ├── LIABILITY (بدهی)                                                   │
│  │   ├── User Deposits                                                  │
│  │   │   ├── User Deposit - IRR                                        │
│  │   │   ├── User Deposit - USD                                        │
│  │   │   ├── User Deposit - USDT TRC20                                 │
│  │   │   ├── User Deposit - BTC                                        │
│  │   │   └── ... (per currency)                                        │
│  │   ├── Pending Withdrawals                                            │
│  │   ├── Escrow Liability                                               │
│  │   ├── Merchant Settlement Payable                                    │
│  │   └── Partner Payable                                                │
│  │                                                                      │
│  ├── EQUITY (سرمایه)                                                    │
│  │   ├── Platform Capital                                               │
│  │   ├── Revenue Reserve                                                │
│  │   └── Retained Earnings                                              │
│  │                                                                      │
│  ├── REVENUE (درآمد)                                                    │
│  │   ├── Exchange Fee Revenue                                           │
│  │   ├── Withdrawal Fee Revenue                                         │
│  │   ├── FX Spread Revenue                                              │
│  │   ├── Card Fee Revenue                                               │
│  │   ├── Subscription Revenue                                           │
│  │   └── Service Fee Revenue                                            │
│  │                                                                      │
│  └── EXPENSE (هزینه)                                                    │
│      ├── Network Fee Expense (blockchain gas)                           │
│      ├── PSP Fee Expense                                                │
│      ├── Partner Fee Expense                                            │
│      ├── Operational Expense                                            │
│      └── Bad Debt Expense                                               │
└─────────────────────────────────────────────────────────────────────────┘
```

## 6.3 Journal Entry Examples

### 6.3.1 Deposit (User deposits 1000 USDT via TRC20)

```
Journal Entry #JE-20260723-001
Date: 2026-07-23 10:30:00 UTC
Type: DEPOSIT
Reference: deposit_req_xyz789
Status: POSTED

┌──────────────────────────────────────────────────────────┐
│  Debit (بدهکار)          │  Credit (بستانکار)            │
│                           │                               │
│  Hot Wallet USDT TRC20    │  User Deposit USDT            │
│  +1,000.00 USDT           │  +1,000.00 USDT               │
│                           │                               │
│  مجموع: 1,000.00          │  مجموع: 1,000.00              │
└──────────────────────────────────────────────────────────┘

✓ Debit = Credit → Balanced
```

### 6.3.2 Withdrawal (User withdraws 500 USDT via TRC20, fee 1 USDT)

```
Journal Entry #JE-20260723-002
Date: 2026-07-23 11:00:00 UTC
Type: WITHDRAWAL
Reference: withdrawal_req_abc456
Status: POSTED

┌──────────────────────────────────────────────────────────┐
│  Debit (بدهکار)          │  Credit (بستانکار)            │
│                           │                               │
│  User Deposit USDT        │  Hot Wallet USDT TRC20        │
│  -500.00 USDT             │  -501.00 USDT                 │
│                           │                               │
│                           │  Network Fee Expense           │
│                           │  -1.00 USDT                    │
│                           │                               │
│  مجموع: 500.00            │  مجموع: 502.00                │
└──────────────────────────────────────────────────────────┘

⚠️ اینجا Debit ≠ Credit — باید Fee هم ثبت شود:

صحیح:
┌──────────────────────────────────────────────────────────┐
│  Debit (بدهکار)          │  Credit (بستانکار)            │
│                           │                               │
│  User Deposit USDT        │  Hot Wallet USDT TRC20        │
│  -501.00 USDT             │  -501.00 USDT                 │
│                           │                               │
│  مجموع: 501.00            │  مجموع: 501.00                │
└──────────────────────────────────────────────────────────┘

سپس Fee Entry جداگانه:
┌──────────────────────────────────────────────────────────┐
│  Debit (بدهکار)          │  Credit (بستانکار)            │
│                           │                               │
│  User Deposit USDT        │  Withdrawal Fee Revenue       │
│  -1.00 USDT               │  +1.00 USDT                   │
│                           │                               │
│  مجموع: 1.00              │  مجموع: 1.00                  │
└──────────────────────────────────────────────────────────┘
```

### 6.3.3 Exchange (User converts 100 USD → 95 EUR, fee 2 USD)

```
Journal Entry #JE-20260723-003
Date: 2026-07-23 12:00:00 UTC
Type: EXCHANGE
Reference: exchange_req_def789
Status: POSTED

Entry 1: Deduct source currency
┌──────────────────────────────────────────────────────────┐
│  Debit                      │  Credit                     │
│                             │                             │
│  User Deposit USD           │  User Deposit EUR           │
│  -102.00 USD                │  +95.00 EUR                 │
│                             │                             │
│                             │  FX Spread Revenue           │
│                             │  +1.00 EUR (spread)          │
│                             │                             │
│                             │  Exchange Fee Revenue        │
│                             │  +2.00 USD → converted to EUR│
└──────────────────────────────────────────────────────────┘

(ساختار دقیق‌تر با FX rate conversion در ادامه)
```

### 6.3.4 P2P Transfer (User A sends 200 USDT to User B)

```
Journal Entry #JE-20260723-004
Date: 2026-07-23 13:00:00 UTC
Type: INTERNAL_TRANSFER
Reference: transfer_req_ghi012
Status: POSTED

┌──────────────────────────────────────────────────────────┐
│  Debit (بدهکار)          │  Credit (بستانکار)            │
│                           │                               │
│  User A Deposit USDT      │  User B Deposit USDT          │
│  -200.00 USDT             │  +200.00 USDT                 │
│                           │                               │
│  مجموع: 200.00            │  مجموع: 200.00                │
└──────────────────────────────────────────────────────────┘

⚠️ هیچ موجودی مستقیماً تغییر نمی‌کند.
⚠️ موجودی User A = sum(所有 entries where account = user_a_deposit_usdt)
⚠️ موجودی User B = sum(所有 entries where account = user_b_deposit_usdt)
```

## 6.4 Ledger Database Schema

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    LEDGER DATABASE SCHEMA                                 │
│                                                                          │
│  ┌─────────────────────────────────────────────────────┐                │
│  │  ledger_accounts                                     │                │
│  │  ├── id (UUID, PK)                                  │                │
│  │  ├── code (VARCHAR, UNIQUE) — "USER_USR001_DEPOSIT_USDT"            │
│  │  ├── name (VARCHAR)                                  │                │
│  │  ├── type (ENUM: ASSET, LIABILITY, EQUITY, REVENUE, EXPENSE)        │
│  │  ├── currency_code (VARCHAR)                         │                │
│  │  ├── parent_account_id (UUID, FK — self-ref)        │                │
│  │  ├── is_active (BOOLEAN)                             │                │
│  │  ├── normal_balance (ENUM: DEBIT, CREDIT)            │                │
│  │  ├── metadata (JSONB)                                │                │
│  │  ├── created_at (TIMESTAMP)                          │                │
│  │  └── updated_at (TIMESTAMP)                          │                │
│  └─────────────────────────────────────────────────────┘                │
│                                                                          │
│  ┌─────────────────────────────────────────────────────┐                │
│  │  journal_entries                                     │                │
│  │  ├── id (UUID, PK)                                  │                │
│  │  ├── entry_number (BIGINT, SEQUENCE) — monotonic    │                │
│  │  ├── entry_type (ENUM: DEPOSIT, WITHDRAWAL, TRANSFER,                │
│  │  │                EXCHANGE, FEE, SETTLEMENT, ADJUSTMENT)             │
│  │  ├── reference_type (VARCHAR) — "deposit_request"   │                │
│  │  ├── reference_id (UUID) — link to source entity    │                │
│  │  ├── description (TEXT)                              │                │
│  │  ├── status (ENUM: PENDING, POSTED, REVERSED)       │                │
│  │  ├── posted_at (TIMESTAMP)                           │                │
│  │  ├── reversed_at (TIMESTAMP)                         │                │
│  │  ├── reversal_of (UUID, FK → journal_entries)       │                │
│  │  ├── idempotency_key (VARCHAR, UNIQUE)              │                │
│  │  ├── metadata (JSONB)                                │                │
│  │  └── created_at (TIMESTAMP)                          │                │
│  └─────────────────────────────────────────────────────┘                │
│                                                                          │
│  ┌─────────────────────────────────────────────────────┐                │
│  │  journal_lines (Entry Lines)                         │                │
│  │  ├── id (UUID, PK)                                  │                │
│  │  ├── journal_entry_id (UUID, FK → journal_entries)  │                │
│  │  ├── account_id (UUID, FK → ledger_accounts)        │                │
│  │  ├── direction (ENUM: DEBIT, CREDIT)                │                │
│  │  ├── amount (DECIMAL(28,12)) — high precision       │                │
│  │  ├── currency_code (VARCHAR)                         │                │
│  │  ├── amount_base_currency (DECIMAL) — for reporting │                │
│  │  ├── running_balance (DECIMAL) — optional snapshot   │                │
│  │  └── created_at (TIMESTAMP)                          │                │
│  │                                                      │                │
│  │  CONSTRAINT: sum(debits) = sum(credits) per journal  │                │
│  └─────────────────────────────────────────────────────┘                │
│                                                                          │
│  ┌─────────────────────────────────────────────────────┐                │
│  │  balance_snapshots (Materialized — for performance)  │                │
│  │  ├── id (UUID, PK)                                  │                │
│  │  ├── account_id (UUID, FK)                          │                │
│  │  ├── balance (DECIMAL(28,12))                        │                │
│  │  ├── snapshot_at (TIMESTAMP)                         │                │
│  │  ├── last_entry_id (UUID, FK)                       │                │
│  │  └── checksum (VARCHAR) — integrity verification    │                │
│  │                                                      │                │
│  │  این جدول یک Materialized Snapshot است.              │                │
│  │  موجودی واقعی همیشه از sum(lines) محاسبه می‌شود.    │                │
│  │  این جدول فقط برای performance optimization است.    │                │
│  └─────────────────────────────────────────────────────┘                │
│                                                                          │
│  ┌─────────────────────────────────────────────────────┐                │
│  │  reconciliation_records                              │                │
│  │  ├── id (UUID, PK)                                  │                │
│  │  ├── account_id (UUID, FK)                          │                │
│  │  ├── ledger_balance (DECIMAL)                        │                │
│  │  ├── external_balance (DECIMAL) — from blockchain/bank│               │
│  │  ├── difference (DECIMAL)                            │                │
│  │  ├── status (ENUM: MATCHED, MISMATCHED, RESOLVED)   │                │
│  │  ├── reconciled_at (TIMESTAMP)                       │                │
│  │  ├── resolved_at (TIMESTAMP)                         │                │
│  │  └── notes (TEXT)                                    │                │
│  └─────────────────────────────────────────────────────┘                │
│                                                                          │
│  INDEXES:                                                                │
│  ├── journal_entries: (reference_type, reference_id)                    │
│  ├── journal_entries: (idempotency_key) UNIQUE                          │
│  ├── journal_entries: (posted_at)                                       │
│  ├── journal_lines: (account_id, created_at)                            │
│  ├── journal_lines: (journal_entry_id)                                  │
│  └── balance_snapshots: (account_id, snapshot_at)                       │
│                                                                          │
│  PARTITIONING:                                                           │
│  └── journal_lines: RANGE partition by created_at (monthly)             │
└─────────────────────────────────────────────────────────────────────────┘
```

## 6.5 Ledger Invariants (Constraints)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    LEDGER INVARIANTS                                      │
│                                                                          │
│  1. JOURNAL BALANCE CONSTRAINT:                                         │
│     SELECT journal_entry_id,                                            │
│            SUM(CASE WHEN direction='DEBIT' THEN amount ELSE 0 END) as debits, │
│            SUM(CASE WHEN direction='CREDIT' THEN amount ELSE 0 END) as credits│
│     FROM journal_lines                                                  │
│     GROUP BY journal_entry_id                                           │
│     HAVING debits != credits  → این کوئری باید همیشه خالی برگردد       │
│                                                                          │
│  2. NO DIRECT BALANCE MODIFICATION:                                     │
│     balance_snapshots فقط توسط Ledger Service قابل نوشتن است            │
│     Wallet Service نمی‌تواند موجودی را مستقیماً تغییر دهد              │
│                                                                          │
│  3. IMMUTABILITY:                                                       │
│     journal_entries: UPDATE/DELETE ممنوع                                │
│     journal_lines: UPDATE/DELETE ممنوع                                  │
│     Correction → فقط با Reversal Entry جدید                             │
│                                                                          │
│  4. IDEMPOTENCY:                                                        │
│     هر عملیات باید idempotency_key منحصربه‌فرد داشته باشد              │
│     تکرار عملیات با همان key → همان نتیجه (بدون double-entry)          │
│                                                                          │
│  5. ATOMICITY:                                                          │
│     هر journal entry + تمام خطوط آن در یک تراکنش دیتابیس ثبت می‌شوند  │
│                                                                          │
│  6. EVENT SOURCING:                                                     │
│     موجودی فعلی = sum(所有 journal lines for that account)               │
│     اگر دیتابیس از بین برود، از event log قابل بازسازی است             │
└─────────────────────────────────────────────────────────────────────────┘
```

---

# 7. مرحله ششم: طراحی Exchange Engine

## 7.1 معماری Exchange Engine

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    EXCHANGE ENGINE ARCHITECTURE                           │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  PRICE FEED AGGREGATOR                                          │    │
│  │                                                                  │    │
│  │  Sources:                                                        │    │
│  │  ├── Binance API (Fiat + Crypto)                                │    │
│  │  ├── CoinGecko API (Crypto)                                     │    │
│  │  ├── CoinMarketCap API (Crypto)                                 │    │
│  │  ├── Central Bank Rates (IRR, CNY, RUB — Fiat)                  │    │
│  │  ├── ECB Rates (EUR reference)                                   │    │
│  │  ├── FreeForex API (Fiat pairs)                                  │    │
│  │  ├── Local Market Rates (P2P rates from LocalBitcoins, etc.)    │    │
│  │  └── Internal Rate (for illiquid pairs)                          │    │
│  │                                                                  │    │
│  │  Process:                                                        │    │
│  │  1. Fetch rates from all sources (every 5-30 seconds)           │    │
│  │  2. Remove outliers (>2σ from median)                            │    │
│  │  3. Calculate weighted average                                   │    │
│  │  4. Apply configurable spread per pair                           │    │
│  │  5. Cache in Redis (TTL: 10-60 seconds)                         │    │
│  │  6. Publish rate change events to Kafka                          │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                           │                                              │
│                           ▼                                              │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  RATE ENGINE                                                    │    │
│  │                                                                  │    │
│  │  ┌──────────────────────────────────────────────────────┐       │    │
│  │  │  Currency Pair Configuration                          │       │    │
│  │  │  ├── Pair: USD/IRR                                    │       │    │
│  │  │  ├── Source: Central Bank + Free Market               │       │    │
│  │  │  ├── Base Spread: 0.5%                                │       │    │
│  │  │  ├── Volume-based spread tiers:                       │       │    │
│  │  │  │   ├── < $1000: 0.5%                                │       │    │
│  │  │  │   ├── $1000-$10000: 0.3%                           │       │    │
│  │  │  │   └── > $10000: 0.15%                              │       │    │
│  │  │  ├── Min exchange amount: 10 USD                       │       │    │
│  │  │  ├── Max exchange amount: 50,000 USD                   │       │    │
│  │  │  └── Rate lock duration: 30 seconds                    │       │    │
│  │  └──────────────────────────────────────────────────────┘       │    │
│  │                                                                  │    │
│  │  ┌──────────────────────────────────────────────────────┐       │    │
│  │  │  Cross-Rate Calculation                               │       │    │
│  │  │                                                      │       │    │
│  │  │  اگر جفت‌ارز مستقیم وجود نداشته باشد:               │       │    │
│  │  │  CNY/IRR = CNY/USD × USD/IRR                         │       │    │
│  │  │  BTC/IRR = BTC/USD × USD/IRR                         │       │    │
│  │  │                                                      │       │    │
│  │  │  همیشه بهترین مسیر محاسبه انتخاب می‌شود              │       │    │
│  │  │  (direct > via USD > via BTC > via USDT)             │       │    │
│  │  └──────────────────────────────────────────────────────┘       │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                           │                                              │
│                           ▼                                              │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  EXCHANGE PROCESSOR                                              │    │
│  │                                                                  │    │
│  │  Exchange Types:                                                 │    │
│  │  ┌─────────────────────────────────────────────────────────┐    │    │
│  │  │  1. FIAT ⇄ FIAT                                          │    │    │
│  │  │     User: IRR Wallet → USD Wallet                        │    │    │
│  │  │     Flow: Quote → Confirm → Deduct IRR → Credit USD      │    │    │
│  │  │     Source: Direct market rate or Bank rate               │    │    │
│  │  │                                                          │    │    │
│  │  │  2. FIAT ⇄ CRYPTO                                        │    │    │
│  │  │     User: USD Wallet → USDT Wallet                       │    │    │
│  │  │     Flow: Quote → Confirm → Deduct USD → Credit USDT     │    │    │
│  │  │     Source: Exchange rate (Binance, etc.)                 │    │    │
│  │  │     Note: May route through external exchange             │    │    │
│  │  │                                                          │    │    │
│  │  │  3. CRYPTO ⇄ FIAT                                        │    │    │
│  │  │     User: USDT Wallet → IRR Wallet                       │    │    │
│  │  │     Flow: Quote → Confirm → Deduct USDT → Credit IRR     │    │    │
│  │  │     Source: Exchange rate                                │    │    │
│  │  │                                                          │    │    │
│  │  │  4. CRYPTO ⇄ CRYPTO                                      │    │    │
│  │  │     User: BTC Wallet → USDT Wallet                       │    │    │
│  │  │     Flow: Quote → Confirm → Deduct BTC → Credit USDT     │    │    │
│  │  │     Source: Binance BTC/USDT rate                        │    │    │
│  │  └─────────────────────────────────────────────────────────┘    │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                           │                                              │
│                           ▼                                              │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  FEE ENGINE                                                     │    │
│  │                                                                  │    │
│  │  Fee Types:                                                      │    │
│  │  ├── Exchange Fee (percentage per transaction)                   │    │
│  │  │   └── Tiered by user level/volume                            │    │
│  │  ├── Spread (embedded in exchange rate)                          │    │
│  │  │   └── Configurable per currency pair                         │    │
│  │  ├── Network Fee (for crypto withdrawals)                        │    │
│  │  │   └── Dynamic based on blockchain congestion                 │    │
│  │  └── Priority Fee (for faster processing)                        │    │
│  │      └── User can choose standard/fast/instant                   │    │
│  │                                                                  │    │
│  │  Fee Calculation:                                                │    │
│  │  Total Cost = Exchange Amount × Rate × (1 + Spread%)            │    │
│  │             + Exchange Fee (fixed or %)                          │    │
│  │             + Network Fee (if crypto withdrawal)                  │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  EXCHANGE FLOW (Saga Pattern)                                    │    │
│  │                                                                  │    │
│  │  Step 1: Quote Request                                           │    │
│  │  ├── User specifies: from_currency, to_currency, amount         │    │
│  │  ├── Engine calculates: rate, spread, fee, receive_amount       │    │
│  │  ├── Rate locked for N seconds                                   │    │
│  │  └── Quote ID generated                                          │    │
│  │                                                                  │    │
│  │  Step 2: User Confirms                                           │    │
│  │  ├── Validates rate still valid (not expired)                    │    │
│  │  ├── Validates user has sufficient balance                       │    │
│  │  ├── Validates KYC level allows this amount                      │    │
│  │  ├── Validates AML check passes                                  │    │
│  │  └── Creates exchange_order                                       │    │
│  │                                                                  │    │
│  │  Step 3: Execute (Saga)                                          │    │
│  │  ├── Debit from_currency wallet (Journal Entry)                  │    │
│  │  ├── If external liquidity needed:                               │    │
│  │  │   ├── Route to best external exchange                         │    │
│  │  │   ├── Execute trade                                           │    │
│  │  │   └── Wait for confirmation                                   │    │
│  │  ├── Credit to_currency wallet (Journal Entry)                   │    │
│  │  ├── Record fee (Journal Entry)                                  │    │
│  │  ├── Record exchange in history                                  │    │
│  │  └── Notify user                                                 │    │
│  │                                                                  │    │
│  │  Compensation (if any step fails):                               │    │
│  │  ├── Reverse debit → credit back to source wallet               │    │
│  │  ├── Reverse fee                                                │    │
│  │  ├── Mark exchange_order as FAILED                               │    │
│  │  └── Notify user                                                 │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
```

## 7.2 Liquidity Management

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    LIQUIDITY MANAGEMENT                                   │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  INTERNAL LIQUIDITY (P2P Matching)                               │    │
│  │  ├── Match buy/sell orders internally first                      │    │
│  │  ├── Zero external cost                                          │    │
│  │  └── Only for user-to-user exchanges                             │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  EXTERNAL LIQUIDITY (Exchange Routing)                           │    │
│  │                                                                  │    │
│  │  Tier 1: Direct Partners (lowest cost, fastest)                  │    │
│  │  ├── Binance (best for major crypto pairs)                       │    │
│  │  ├── OKX (alternative crypto)                                    │    │
│  │  └── Local Exchanges (for IRR pairs)                             │    │
│  │                                                                  │    │
│  │  Tier 2: Aggregators                                             │    │
│  │  ├── 1inch (DeFi aggregation)                                    │    │
│  │  └── Paraswap (DeFi aggregation)                                 │    │
│  │                                                                  │    │
│  │  Tier 3: Market Makers                                           │    │
│  │  ├── Contracted market makers for large orders                   │    │
│  │  └── OTC desk for orders > $50,000                               │    │
│  │                                                                  │    │
│  │  Smart Routing Algorithm:                                        │    │
│  │  1. Check internal order book                                    │    │
│  │  2. Query all Tier 1 providers for rate                          │    │
│  │  3. If Tier 1 insufficient, query Tier 2                         │    │
│  │  4. For large orders, split across multiple providers            │    │
│  │  5. Select best rate considering: rate + fee + speed + reliability│    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  RATE LOCKING                                                    │    │
│  │                                                                  │    │
│  │  • Rate locked for 15-60 seconds (configurable per pair)         │    │
│  │  • If market moves > 1% during lock → reject and re-quote        │    │
│  │  • Slippage tolerance: user-configurable (default 0.5%)          │    │
│  │  • For large orders: pre-hedge with external exchange            │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
```

---

# 8. مرحله هفتم: طراحی Settlement Engine

## 8.1 معماری Settlement Engine

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    SETTLEMENT ENGINE ARCHITECTURE                         │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  SETTLEMENT TYPES                                                │    │
│  │                                                                  │    │
│  │  1. USER WALLET SETTLEMENT (Internal)                            │    │
│  │     ├── Transfer between user's own wallets                      │    │
│  │     ├── P2P transfer to another user                             │    │
│  │     └── Real-time, Ledger-only (no external movement)            │    │
│  │                                                                  │    │
│  │  2. BANK SETTLEMENT (External)                                   │    │
│  │     ├── User withdrawal to bank account                          │    │
│  │     ├── User deposit from bank account                           │    │
│  │     ├── Bulk settlement to partner banks                         │    │
│  │     └── Timing: Instant or T+1/T+2                              │    │
│  │                                                                  │    │
│  │  3. PSP SETTLEMENT                                               │    │
│  │     ├── Card payment settlement                                  │    │
│  │     ├── Gateway fee deduction                                    │    │
│  │     └── Timing: T+1 or T+2 (PSP dependent)                     │    │
│  │                                                                  │    │
│  │  4. EXCHANGE SETTLEMENT                                          │    │
│  │     ├── Settlement with external exchanges                       │    │
│  │     ├── Crypto transfer to/from exchange wallet                  │    │
│  │     └── Timing: Depends on blockchain confirmation               │    │
│  │                                                                  │    │
│  │  5. PARTNER SETTLEMENT                                           │    │
│  │     ├── Travel service providers                                 │    │
│  │     ├── eSIM providers                                           │    │
│  │     ├── Insurance providers                                      │    │
│  │     └── Timing: Per agreement (daily/weekly/monthly)             │    │
│  │                                                                  │    │
│  │  6. MERCHANT SETTLEMENT                                          │    │
│  │     ├── QR payment settlement                                    │    │
│  │     ├── Online payment settlement                                │    │
│  │     └── Timing: T+1 or instant (premium tier)                   │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  SETTLEMENT PROCESSING FLOW                                      │    │
│  │                                                                  │    │
│  │  ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐  │    │
│  │  │ Settlement│───►│ Validation│───►│ Risk     │───►│ Execution│  │    │
│  │  │ Request   │    │ Engine   │    │ Check    │    │ Engine   │  │    │
│  │  └──────────┘    └──────────┘    └──────────┘    └──────────┘  │    │
│  │       │                                            │            │    │
│  │       │          ┌──────────┐    ┌──────────┐      │            │    │
│  │       │          │ Ledger   │◄───│ Reconcil.│◄─────┘            │    │
│  │       │          │ Entry    │    │ Engine   │                   │    │
│  │       │          └──────────┘    └──────────┘                   │    │
│  │       │                                                         │    │
│  │       ▼                                                         │    │
│  │  ┌──────────────────────────────────────────────────────┐      │    │
│  │  │ Settlement States:                                     │      │    │
│  │  │ PENDING → VALIDATING → APPROVED → PROCESSING         │      │    │
│  │  │   → EXECUTING → CONFIRMING → SETTLED                  │      │    │
│  │  │                                                        │      │    │
│  │  │ Rejection states:                                      │      │    │
│  │  │ → REJECTED (validation failed)                         │      │    │
│  │  │ → BLOCKED (AML/risk flagged)                           │      │    │
│  │  │ → FAILED (execution error)                             │      │    │
│  │  │ → REVERSED (compensation)                              │      │    │
│  │  └──────────────────────────────────────────────────────┘      │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  APPROVAL WORKFLOWS                                              │    │
│  │                                                                  │    │
│  │  Auto-Approved:                                                  │    │
│  │  ├── Internal wallet transfers (within platform)                 │    │
│  │  ├── Small withdrawals (< threshold per KYC level)              │    │
│  │  └── Repeated trusted withdrawals (same address/account)        │    │
│  │                                                                  │    │
│  │  Manual Approval Required:                                       │    │
│  │  ├── Large withdrawals (> threshold)                             │    │
│  │  ├── First-time withdrawal to new address                        │    │
│  │  ├── High-risk users (AML flag)                                  │    │
│  │  ├── Cross-border large transfers                                │    │
│  │  └── Admin-triggered holds                                       │    │
│  │                                                                  │    │
│  │  Multi-Signature Required:                                       │    │
│  │  ├── Cold wallet movements (> $100,000 equivalent)              │    │
│  │  ├── Treasury operations                                         │    │
│  │  └── Emergency freeze/unfreeze                                   │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  RECONCILIATION ENGINE                                           │    │
│  │                                                                  │    │
│  │  Automated Reconciliation:                                       │    │
│  │  ├── Every N minutes: compare Ledger balance vs Blockchain       │    │
│  │  ├── Every N hours: compare Ledger vs Bank balance              │    │
│  │  ├── Every N hours: compare Ledger vs PSP balance               │    │
│  │  ├── Every N hours: compare Ledger vs Exchange balance          │    │
│  │                                                                  │    │
│  │  Discrepancy Handling:                                           │    │
│  │  ├── Small discrepancy (< threshold): auto-adjust with journal  │    │
│  │  ├── Medium discrepancy: flag for manual review                 │    │
│  │  ├── Large discrepancy: freeze operations + alert               │    │
│  │  └── All discrepancies logged in reconciliation_records          │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
```

## 8.2 Settlement Flow Diagrams

### 8.2.1 User Withdrawal to Bank

```
User Request → Validate KYC/AML → Check Balance → Create Settlement
→ Create Pending Journal Entry (Debit User, Credit Pending Withdrawals)
→ Route to Bank API → Wait for Confirmation
→ On Success: Create Final Journal Entry (Debit Pending, Credit Bank Asset)
→ On Failure: Reverse Journal Entry → Notify User
```

### 8.2.2 Partner Settlement (Daily Batch)

```
Scheduler triggers (e.g., 00:00 UTC daily)
→ Aggregate all transactions with partner since last settlement
→ Calculate net amount per partner per currency
→ Generate settlement report
→ For amounts > threshold: require manual approval
→ Execute settlement (bank transfer / crypto transfer)
→ Create journal entries
→ Reconcile
```

---

# 9. مرحله هشتم: ممیزی امنیت

## 9.1 Wallet Security Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    WALLET SECURITY ARCHITECTURE                           │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  HOT WALLET (Online, ~5% of total funds)                        │    │
│  │                                                                  │    │
│  │  Purpose: Handle user deposits and small withdrawals             │    │
│  │  Risk Level: HIGH (online, attackable)                           │    │
│  │                                                                  │    │
│  │  Security Measures:                                              │    │
│  │  ├── Multi-Signature: 2-of-3 minimum                            │    │
│  │  │   ├── Key 1: Platform operational key (HSM-backed)           │    │
│  │  │   ├── Key 2: Second server (different location)              │    │
│  │  │   └── Key 3: Offline backup key                              │    │
│  │  ├── Spending Limits:                                            │    │
│  │  │   ├── Per transaction: max $10,000 equivalent                │    │
│  │  │   ├── Per hour: max $50,000 equivalent                       │    │
│  │  │   └── Per day: max $200,000 equivalent                       │    │
│  │  ├── Auto-Sweep to Warm Wallet when balance > threshold         │    │
│  │  ├── Real-time monitoring of all outgoing transactions           │    │
│  │  └── IP Whitelisting for signing servers                        │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  WARM WALLET (Semi-online, ~20% of total funds)                 │    │
│  │                                                                  │    │
│  │  Purpose: Replenish Hot Wallet, medium withdrawals               │    │
│  │  Risk Level: MEDIUM                                              │    │
│  │                                                                  │    │
│  │  Security Measures:                                              │    │
│  │  ├── Multi-Signature: 3-of-5 minimum                            │    │
│  │  │   ├── Key 1: CTO key                                        │    │
│  │  │   ├── Key 2: Finance Director key                            │    │
│  │  │   ├── Key 3: Operations key (HSM)                            │    │
│  │  │   ├── Key 4: Security Officer key                            │    │
│  │  │   └── Key 5: Emergency key (offline)                         │    │
│  │  ├── Manual approval required for transfers > $25,000           │    │
│  │  ├── Time-locked transactions (24h delay for large amounts)     │    │
│  │  └── Geographic distribution of signing keys                    │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  COLD WALLET (Offline, ~75% of total funds)                     │    │
│  │                                                                  │    │
│  │  Purpose: Long-term storage, treasury                           │    │
│  │  Risk Level: LOW (offline, air-gapped)                           │    │
│  │                                                                  │    │
│  │  Security Measures:                                              │    │
│  │  ├── Multi-Signature: 4-of-7 minimum                            │    │
│  │  ├── Hardware Security Module (HSM) for key storage              │    │
│  │  ├── Air-gapped signing devices                                  │    │
│  │  ├── Geographic distribution (3+ countries)                     │    │
│  │  ├── Time-locked (72h+ delay) for large movements               │    │
│  │  ├── Physical security (bank vault / secure facility)           │    │
│  │  └── Regular key rotation ceremony (annually)                   │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  HSM READINESS PLAN                                              │    │
│  │                                                                  │    │
│  │  Phase 1 (Launch): Software-based key management                 │    │
│  │  ├── HashiCorp Vault for key storage                             │    │
│  │  ├── Encrypted key files with passphrase                         │    │
│  │  └── Key ceremony documented                                     │    │
│  │                                                                  │    │
│  │  Phase 2 (6 months): Cloud HSM                                   │    │
│  │  ├── AWS CloudHSM or Azure Dedicated HSM                        │    │
│  │  ├── FIPS 140-2 Level 3 compliance                               │    │
│  │  └── Hardware-backed key generation                              │    │
│  │                                                                  │    │
│  │  Phase 3 (12 months): On-premise HSM                             │    │
│  │  ├── Thales Luna or Entrust nShield                              │    │
│  │  ├── For cold wallet key management                              │    │
│  │  └── PCI-DSS compliance                                          │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
```

## 9.2 Application Security

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    APPLICATION SECURITY                                    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  AUTHENTICATION & SESSION SECURITY                               │    │
│  │                                                                  │    │
│  │  ├── Multi-Factor Authentication (MFA)                           │    │
│  │  │   ├── TOTP (Google Authenticator, Authy)                     │    │
│  │  │   ├── SMS OTP (backup, rate-limited)                         │    │
│  │  │   ├── Email OTP (backup)                                     │    │
│  │  │   ├── Biometric (Face ID, Fingerprint) — mobile app          │    │
│  │  │   └── Hardware Key (YubiKey) — for admin accounts            │    │
│  │  │                                                              │    │
│  │  ├── Session Management                                          │    │
│  │  │   ├── JWT with short expiry (15 min access, 7 day refresh)   │    │
│  │  │   ├── Refresh token rotation (one-time use)                  │    │
│  │  │   ├── Token binding to device fingerprint                    │    │
│  │  │   ├── Concurrent session limit (configurable)                │    │
│  │  │   ├── Session invalidation on password change                │    │
│  │  │   └── Idle timeout (30 min for web, configurable)            │    │
│  │  │                                                              │    │
│  │  ├── Device Fingerprinting                                       │    │
│  │  │   ├── Browser fingerprint (canvas, WebGL, fonts)             │    │
│  │  │   ├── Device ID (mobile)                                     │    │
│  │  │   ├── IP geolocation                                         │    │
│  │  │   ├── Trusted device management                              │    │
│  │  │   └── New device alert + step-up authentication              │    │
│  │  │                                                              │    │
│  │  └── Password Policy                                             │    │
│  │      ├── Minimum 12 characters                                  │    │
│  │      ├── Complexity requirements                                 │    │
│  │      ├── Breach database check (HaveIBeenPwned API)             │    │
│  │      ├── Argon2id hashing                                        │    │
│  │      └── Password history (prevent reuse of last 12)            │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  SECRET MANAGEMENT                                               │    │
│  │                                                                  │    │
│  │  ├── HashiCorp Vault                                             │    │
│  │  │   ├── Dynamic secrets (DB credentials, API keys)             │    │
│  │  │   ├── Transit encryption (encrypt/decrypt as a service)      │    │
│  │  │   ├── PKI (certificate management)                            │    │
│  │  │   ├── Key-Value store (app secrets)                           │    │    │
│  │  │   └── Auto-rotation of all secrets                           │    │
│  │  │                                                              │    │
│  │  ├── Key Rotation Policy                                         │    │
│  │  │   ├── API Keys: every 90 days                                │    │
│  │  │   ├── Database credentials: every 30 days                    │    │
│  │  │   ├── JWT signing keys: every 180 days                       │    │
│  │  │   ├── Encryption keys: every 365 days                        │    │
│  │  │   ├── TLS certificates: auto-renewal via Let's Encrypt       │    │
│  │  │   └── Wallet keys: per security level (see above)            │    │
│  │  │                                                              │    │
│  │  └── No Hardcoded Secrets                                        │    │
│  │      ├── Pre-commit hooks (detect-secrets, git-secrets)         │    │
│  │      ├── CI/CD scanning (TruffleHog, GitLeaks)                  │    │
│  │      └── Environment variables via Vault sidecar injection      │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  ENCRYPTION                                                      │    │
│  │                                                                  │    │
│  │  Data at Rest:                                                   │    │
│  │  ├── Database: AES-256 transparent encryption                    │    │
│  │  ├── Sensitive fields: application-level AES-256-GCM            │    │
│  │  │   (PII, bank account numbers, wallet addresses)              │    │
│  │  ├── Backups: encrypted with separate key                        │    │
│  │  └── KMS: AWS KMS / GCP KMS / Vault Transit                    │    │
│  │                                                                  │    │
│  │  Data in Transit:                                                │    │
│  │  ├── External: TLS 1.3 minimum (HSTS enforced)                  │    │
│  │  ├── Internal (service-to-service): mTLS via Service Mesh       │    │
│  │  └── Database connections: TLS required                          │    │
│  │                                                                  │    │
│  │  Data in Use:                                                    │    │
│  │  ├── Sensitive data masked in logs                               │    │
│  │  ├── Memory encryption for key operations (where supported)     │    │
│  │  └── Secure enclave for critical computations (future)          │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  AUDIT & TAMPER DETECTION                                        │    │
│  │                                                                  │    │
│  │  Immutable Audit Log:                                            │    │
│  │  ├── Append-only log (no update/delete)                          │    │
│  │  ├── Hash chain (each entry includes hash of previous)          │    │
│  │  ├── Stored in separate database/storage                         │    │
│  │  ├── Real-time streaming to SIEM                                 │    │
│  │  └── Periodic integrity verification                              │    │
│  │                                                                  │    │
│  │  What is Logged:                                                 │    │
│  │  ├── All authentication events (login, logout, MFA)              │    │
│  │  ├── All financial transactions                                  │    │
│  │  ├── All admin actions                                           │    │
│  │  ├── All API access (with request/response summary)              │    │
│  │  ├── All configuration changes                                   │    │
│  │  ├── All permission changes                                      │    │
│  │  ├── All data access to sensitive records                        │    │
│  │  └── All system events (startup, shutdown, errors)              │    │
│  │                                                                  │    │
│  │  Tamper Detection:                                               │    │
│  │  ├── Hash chain verification (periodic)                          │    │
│  │  ├── Anomaly detection on audit log patterns                    │    │
│  │  ├── Alert on log gaps (missing entries)                         │    │
│  │  └── Write-once storage (WORM) for compliance logs              │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  BACKUP STRATEGY                                                 │    │
│  │                                                                  │    │
│  │  Database Backups:                                               │    │
│  │  ├── Continuous: WAL archiving (Point-in-Time Recovery)          │    │
│  │  ├── Hourly: Incremental snapshots                               │    │
│  │  ├── Daily: Full backup                                          │    │
│  │  ├── Weekly: Full backup to separate region                      │    │
│  │  ├── Monthly: Archive to cold storage                            │    │
│  │  └── Retention: 90 days daily, 1 year monthly                   │    │
│  │                                                                  │    │
│  │  Backup Testing:                                                 │    │
│  │  ├── Monthly restore test to staging environment                │    │
│  │  ├── RPO: < 5 minutes (WAL-based)                               │    │
│  │  └── RTO: < 1 hour (automated failover)                         │    │
│  │                                                                  │    │
│  │  Wallet Backup:                                                  │    │
│  │  ├── HD wallet seed phrase: encrypted, split across 3 locations  │    │
│  │  ├── Private keys: HSM-backed or encrypted in Vault              │    │
│  │  └── Regular verification of backup integrity                    │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  WITHDRAWAL APPROVAL ENGINE                                      │    │
│  │                                                                  │    │
│  │  ┌─────────────────────────────────────────────────────┐        │    │
│  │  │  Withdrawal Request                                  │        │    │
│  │  │  ↓                                                   │        │    │
│  │  │  1. Basic Validation (amount, address, KYC)          │        │    │
│  │  │  ↓                                                   │        │    │
│  │  │  2. AML Check (transaction monitoring)               │        │    │
│  │  │  ↓                                                   │        │    │
│  │  │  3. Risk Score Assessment                            │        │    │
│  │  │  ↓                                                   │        │    │
│  │  │  4. Velocity Check (rate, pattern)                   │        │    │
│  │  │  ↓                                                   │        │    │
│  │  │  5. Device/IP Verification                           │        │    │
│  │  │  ↓                                                   │        │    │
│  │  │  6. Balance Sufficiency Check                        │        │    │
│  │  │  ↓                                                   │        │    │
│  │  │  Decision:                                           │        │    │
│  │  │  ├── Auto-Approve (low risk, small amount, trusted)  │        │    │
│  │  │  ├── Manual Review (medium risk/amount)              │        │    │
│  │  │  ├── Multi-Approve (high amount)                     │        │    │
│  │  │  └── Auto-Reject (AML flag, blacklist)               │        │    │
│  │  └─────────────────────────────────────────────────────┘        │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
```

---

# 10. مرحله نهم: تحلیل ریسک مسدودی دارایی‌ها

## 10.1 تهدیدات اصلی

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    ASSET FREEZE RISK ANALYSIS                             │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  THREAT 1: Exchange Account Freeze                               │    │
│  │                                                                  │    │
│  │  Scenario: Exchange (Binance, OKX) freezes platform account      │    │
│  │  Impact: Loss of access to trading + stuck funds                 │    │
│  │  Probability: MEDIUM-HIGH                                        │    │
│  │                                                                  │    │
│  │  Mitigation:                                                     │    │
│  │  ├── Use 3+ exchanges simultaneously                             │    │
│  │  ├── No more than 20% of crypto holdings on any single exchange │    │
│  │  ├── Regular sweep from exchange to platform wallets             │    │
│  │  ├── Use DEX (decentralized exchanges) as fallback              │    │
│  │  ├── Maintain OTC relationships for large trades                │    │
│  │  └── Use exchange sub-accounts with isolated balances           │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  THREAT 2: Bank Account Freeze                                   │    │
│  │                                                                  │    │
│  │  Scenario: Regulatory action freezes bank accounts               │    │
│  │  Impact: Cannot process fiat deposits/withdrawals                │    │
│  │  Probability: MEDIUM                                             │    │
│  │                                                                  │    │
│  │  Mitigation:                                                     │    │
│  │  ├── Multiple bank accounts across different banks               │    │
│  │  ├── Accounts in multiple jurisdictions                          │    │
│  │  ├── Keep operational funds spread across accounts               │    │
│  │  ├── Maintain crypto-only mode capability (fiat fallback)       │    │
│  │  ├── Pre-established alternative banking relationships           │    │
│  │  └── Swift recovery plan: switch to backup bank within 1 hour   │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  THREAT 3: Blockchain Wallet Freeze (Blacklist)                  │    │
│  │                                                                  │    │
│  │  Scenario: USDT issuer (Tether) blacklists platform addresses   │    │
│  │  Impact: USDT on that address becomes unmovable                  │    │
│  │  Probability: LOW-MEDIUM                                         │    │
│  │                                                                  │    │
│  │  Mitigation:                                                     │    │
│  │  ├── Distribute funds across many addresses (HD wallet)         │    │
│  │  ├── Use multiple stablecoins (USDT, USDC, DAI, TUSD)          │    │
│  │  ├── No single address holds more than $50K equivalent          │    │
│  │  ├── Auto-rotate receiving addresses                             │    │
│  │  ├── Monitor blacklists proactively                              │    │
│  │  └── Maintain native crypto holdings (BTC, ETH) as backup       │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  THREAT 4: PSP/Payment Provider Disruption                       │    │
│  │                                                                  │    │
│  │  Scenario: Primary PSP terminates contract or blocks service     │    │
│  │  Impact: Cannot process card payments / bank transfers           │    │
│  │  Probability: MEDIUM                                             │    │
│  │                                                                  │    │
│  │  Mitigation:                                                     │    │
│  │  ├── Always have 2+ PSP integrations active                     │    │
│  │  ├── Smart routing: auto-failover to backup PSP                 │    │
│  │  ├── Regular testing of backup PSP (at least monthly)           │    │
│  │  ├── Diversify across PSP types (bank, aggregator, direct)      │    │
│  │  └── Maintain direct bank transfer as last resort               │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
```

## 10.2 Liquidity Distribution Strategy

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    LIQUIDITY DISTRIBUTION MODEL                           │
│                                                                          │
│  Target Distribution (Crypto Holdings):                                  │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                                                                  │    │
│  │  Platform Hot Wallets:     10%  (across 5+ addresses)           │    │
│  │  Platform Warm Wallets:    15%  (across 3+ addresses)           │    │
│  │  Platform Cold Wallets:    40%  (across 2+ locations)           │    │
│  │  Exchange A (Binance):     10%  (sub-accounts)                   │    │
│  │  Exchange B (OKX):         10%  (sub-accounts)                   │    │
│  │  Exchange C (Bybit/KuCoin): 5%  (sub-accounts)                  │    │
│  │  DEX Liquidity:            5%   (for decentralized fallback)    │    │
│  │  OTC Reserve:              5%   (for large trades)              │    │
│  │                                                                  │    │
│  │  Total: 100%                                                     │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  Target Distribution (Fiat Holdings):                                    │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │                                                                  │    │
│  │  Operating Account (Bank A): 30%                                 │    │
│  │  Operating Account (Bank B): 20%                                 │    │
│  │  Reserve Account (Bank C):   25%                                 │    │
│  │  PSP Settlement Float:       15%                                 │    │
│  │  Cash Buffer:                10%                                 │    │
│  │                                                                  │    │
│  │  Rebalance trigger: if any account > 40% or < 10%              │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  Automated Rebalancing:                                                  │
│  ├── Monitor balances every 5 minutes                                   │
│  ├── If Hot Wallet > threshold → sweep to Warm                         │
│  ├── If Hot Wallet < minimum → replenish from Warm                     │
│  ├── If Exchange balance > max → withdraw to Cold                      │
│  ├── If Exchange balance < min → transfer from Warm                    │
│  └── All rebalancing logged, approved, and reconciled                  │
└─────────────────────────────────────────────────────────────────────────┘
```

## 10.3 Partner Failover Strategy

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    PARTNER FAILOVER MATRIX                                │
│                                                                          │
│  ┌──────────────┬──────────────┬──────────────┬──────────────┐          │
│  │  Service     │  Primary     │  Secondary   │  Tertiary    │          │
│  ├──────────────┼──────────────┼──────────────┼──────────────┤          │
│  │  Crypto      │  Binance     │  OKX         │  DEX (1inch) │          │
│  │  Exchange    │              │  Bybit       │  KuCoin      │          │
│  ├──────────────┼──────────────┼──────────────┼──────────────┤          │
│  │  Fiat PSP    │  PSP A       │  PSP B       │  Direct Bank │          │
│  │  (Card)      │              │              │  Transfer    │          │
│  ├──────────────┼──────────────┼──────────────┼──────────────┤          │
│  │  Bank        │  Bank A      │  Bank B      │  Bank C      │          │
│  │  Transfer    │  (Domestic)  │  (Domestic)  │  (Intl)      │          │
│  ├──────────────┼──────────────┼──────────────┼──────────────┤          │
│  │  KYC         │  Provider A  │  Provider B  │  Manual      │          │
│  │  Verification│  (SumSub)    │  (Onfido)    │  Review      │          │
│  ├──────────────┼──────────────┼──────────────┼──────────────┤          │
│  │  SMS         │  Twilio      │  Vonage      │  Local       │          │
│  │  Provider    │              │              │  Provider    │          │
│  ├──────────────┼──────────────┼──────────────┼──────────────┤          │
│  │  Email       │  AWS SES     │  SendGrid    │  Self-hosted │          │
│  │  Provider    │              │              │  (Postfix)   │          │
│  ├──────────────┼──────────────┼──────────────┼──────────────┤          │
│  │  Push        │  FCM + APNs  │  OneSignal   │  WebSocket   │          │
│  │  Notification│              │              │  Fallback    │          │
│  ├──────────────┼──────────────┼──────────────┼──────────────┤          │
│  │  Blockchain  │  Self-hosted │  Infura/     │  Public RPC  │          │
│  │  Node        │  Node        │  Alchemy     │  (Rate-ltd)  │          │
│  ├──────────────┼──────────────┼──────────────┼──────────────┤          │
│  │  FX Rates    │  Primary API │  Secondary   │  Manual Rate │          │
│  │              │              │  API         │  (Emergency) │          │
│  └──────────────┴──────────────┴──────────────┴──────────────┘          │
│                                                                          │
│  Failover Rules:                                                         │
│  ├── Auto-failover: if primary responds with error for > 3 consecutive  │
│  │   requests OR latency > 5 seconds                                    │
│  ├── Health check: probe primary every 30 seconds when on secondary     │
│  ├── Auto-recovery: switch back to primary when healthy for > 1 minute  │
│  ├── Alert: always alert on failover (PagerDuty/Slack/Telegram)         │
│  └── Manual override: admin can force routing to specific provider      │
└─────────────────────────────────────────────────────────────────────────┘
```

---

# 11. مرحله دهم: سرویس‌های مالی آینده — Architecture Readiness

## 11.1 معماری Plugin-Based برای سرویس‌های جدید

```
┌─────────────────────────────────────────────────────────────────────────┐
│                EXTENSIBILITY ARCHITECTURE                                 │
│                                                                          │
│  اصل کلیدی: هر سرویس جدید به‌صورت Plugin به سیستم اضافه می‌شود         │
│  بدون تغییر در Core Services (Wallet, Ledger, Identity)                 │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  SERVICE REGISTRY & LIFECYCLE                                    │    │
│  │                                                                  │    │
│  │  interface ServicePlugin {                                       │    │
│  │    serviceId: string                                             │    │
│  │    version: string                                               │    │
│  │    capabilities: Capability[]                                    │    │
│  │    requiredPermissions: Permission[]                             │    │
│  │    healthCheck(): HealthStatus                                   │    │
│  │    onActivate(): void                                            │    │
│  │    onDeactivate(): void                                          │    │
│  │  }                                                               │    │
│  │                                                                  │    │
│  │  interface Capability {                                          │    │
│  │    type: "DEBIT_WALLET" | "CREDIT_WALLET" | "READ_BALANCE"       │    │
│  │          | "CREATE_ORDER" | "PROCESS_PAYMENT" | ...              │    │
│  │    currencies: string[]                                          │    │
│  │    limits: LimitConfig                                           │    │
│  │  }                                                               │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  EVENT BUS INTEGRATION                                           │    │
│  │                                                                  │    │
│  │  هر سرویس جدید می‌تواند:                                        │    │
│  │  ├── Events منتشر کند (مثلاً "order.completed")                 │    │
│  │  ├── Events گوش دهد (مثلاً "payment.received")                  │    │
│  │  ├── Wallet operations انجام دهد (از طریق Wallet API)          │    │
│  │  └── Ledger entries ثبت کند (از طریق Ledger API)               │    │
│  │                                                                  │    │
│  │  Example Events:                                                 │    │
│  │  ├── service.{serviceId}.order.created                           │    │
│  │  ├── service.{serviceId}.order.completed                         │    │
│  │  ├── service.{serviceId}.order.cancelled                         │    │
│  │  ├── service.{serviceId}.payment.required                        │    │
│  │  └── service.{serviceId}.refund.issued                           │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
```

## 11.2 Service Readiness Matrix

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                FUTURE SERVICES ARCHITECTURE READINESS                               │
│                                                                                    │
│  ┌────────────────────┬──────────────────────────────────────────────────────────┐│
│  │ Service             │ Architecture Notes                                       ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ International       │ • Corridor-based routing (Country A → Country B)        ││
│  │ Remittance          │ • Multiple provider integration (Wise, Remitly, etc.)   ││
│  │                     │ • Fee comparison engine                                  ││
│  │                     │ • Delivery time estimation                               ││
│  │                     │ • Compliance per corridor (sanctions, limits)            ││
│  │                     │ • Real-time tracking                                     ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Currency Buy/Sell   │ • Covered by Exchange Engine + Fiat On/Off Ramp        ││
│  │                     │ • Bank integration for fiat delivery                     ││
│  │                     │ • Rate markup configuration                              ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ QR Payment          │ • EMVCo QR standard support                              ││
│  │                     │ • Static QR (merchant) + Dynamic QR (per transaction)    ││
│  │                     │ • Scan-to-pay + Display-to-scan                          ││
│  │                     │ • Cross-border QR (Alipay, WeChat, UnionPay)            ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Virtual Card        │ • Card Issuer API integration (Marqeta, Stripe Issuing) ││
│  │                     │ • Real-time authorization webhook                        ││
│  │                     │ • Spending limits & controls                             ││
│  │                     │ • MCC (Merchant Category Code) restrictions              ││
│  │                     │ • Card-to-wallet instant settlement                      ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Physical Card       │ • Same as Virtual + fulfillment logistics               ││
│  │                     │ • PIN management                                         ││
│  │                     │ • Card activation flow                                   ││
│  │                     │ • Lost/stolen replacement                                ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ eSIM               │ • Provider API integration (Airalo, eSIM Go)            ││
│  │                     │ • Plan catalog management                                ││
│  │                     │ • QR code delivery                                       ││
│  │                     │ • Usage monitoring (if API available)                    ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Mobile Recharge     │ • Aggregator API (Reloadly, Ding)                       ││
│  │                     │ • Country/operator detection                             ││
│  │                     │ • Auto-recharge subscription                             ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Bill Payment        │ • Utility company API integration                       ││
│  │                     │ • Bill scanning (OCR)                                    ││
│  │                     │ • Auto-pay scheduling                                    ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Hotel Booking       │ • OTA API integration (Booking, Expedia, HotelBeds)     ││
│  │                     │ • Search → Book → Pay flow                               ││
│  │                     │ • Cancellation management                                ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Flight/Ticket       │ • GDS API integration (Amadeus, Sabre)                  ││
│  │                     │ • Search → Book → Pay flow                               ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Travel Insurance    │ • Insurance provider API                                 ││
│  │                     │ • Policy generation                                      ││
│  │                     │ • Claim processing                                       ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Airport Services    │ • Lounge access (LoungeKey, Priority Pass)              ││
│  │                     │ • Fast track, meet & greet                               ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Alipay / WeChat     │ • Cross-border payment gateway integration              ││
│  │ / UnionPay QR       │ • Currency conversion at point of sale                  ││
│  │                     │ • Merchant settlement                                    ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Merchant Payment    │ • POS integration                                        ││
│  │                     │ • Payment link generation                                ││
│  │                     │ • Invoice system                                         ││
│  │                     │ • Multi-merchant dashboard                               ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ P2P Transfer        │ • Covered by Wallet Service internal transfer           ││
│  │                     │ • Phone/email/username based                             ││
│  │                     │ • Request money feature                                  ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Escrow              │ • Milestone-based release                                ││
│  │                     │ • Dispute resolution workflow                            ││
│  │                     │ • Multi-party support                                    ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Subscription        │ • Recurring billing engine                               ││
│  │                     │ • Dunning management                                     ││
│  │                     │ • Plan upgrades/downgrades                               ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Cashback            │ • Rule-based cashback engine                             ││
│  │                     │ • Merchant-specific rates                                ││
│  │                     │ • Tiered cashback by user level                          ││
│  ├────────────────────┼──────────────────────────────────────────────────────────┤│
│  │ Loyalty             │ • Points engine                                          ││
│  │                     │ • Tier management (Silver/Gold/Platinum)                 ││
│  │                     │ • Reward catalog                                         ││
│  │                     │ • Referral tracking                                      ││
│  └────────────────────┴──────────────────────────────────────────────────────────┘│
└────────────────────────────────────────────────────────────────────────────────────┘
```

---

# 12. مرحله یازدهم: تحلیل ماژول‌ها

## 12.1 Detailed Module Analysis

### Module 1: Identity Service

| بُعد | تحلیل |
|---|---|
| **وضعیت فعلی** | ❌ وجود ندارد |
| **مشکلات** | عدم وجود سیستم احراز هویت |
| **ریسک‌ها** | بدون احراز هویت، هیچ سرویس مالی قابل ارائه نیست |
| **اولویت اصلاح** | 🔴 P0 — بحرانی (اولین سرویسی که باید ساخته شود) |
| **هزینه فنی** | متوسط (2-4 هفته برای نسخه اولیه) |
| **زمان پیاده‌سازی** | 4-6 هفته (شامل MFA، RBAC، Device Management) |
| **تأثیر بر سیستم** | 🔴 بسیار بالا — زیرساخت تمام سرویس‌ها |

### Module 2: User Service

| بُعد | تحلیل |
|---|---|
| **وضعیت فعلی** | ❌ وجود ندارد |
| **مشکلات** | عدم مدیریت پروفایل، KYC، مستندات |
| **ریسک‌ها** | بدون KYC، امکان انطباق با مقررات وجود ندارد |
| **اولویت اصلاح** | 🔴 P0 — بحرانی |
| **هزینه فنی** | متوسط |
| **زمان پیاده‌سازی** | 3-5 هفته |
| **تأثیر بر سیستم** | 🔴 بسیار بالا |

### Module 3: Wallet Service

| بُعد | تحلیل |
|---|---|
| **وضعیت فعلی** | ❌ وجود ندارد |
| **مشکلات** | عدم وجود سیستم کیف پول |
| **ریسک‌ها** | بدون Wallet، هیچ عملیات مالی ممکن نیست |
| **اولویت اصلاح** | 🔴 P0 — بحرانی |
| **هزینه فنی** | بالا (multi-currency, multi-chain complexity) |
| **زمان پیاده‌سازی** | 6-10 هفته |
| **تأثیر بر سیستم** | 🔴 بسیار بالا — قلب سیستم مالی |

### Module 4: Ledger Service

| بُعد | تحلیل |
|---|---|
| **وضعیت فعلی** | ❌ وجود ندارد |
| **مشکلات** | عدم سیستم حسابداری |
| **ریسک‌ها** | بدون Ledger، ردیابی مالی و reconciliation غیرممکن |
| **اولویت اصلاح** | 🔴 P0 — بحرانی |
| **هزینه فنی** | بالا (Double-Entry precision, high throughput) |
| **زمان پیاده‌سازی** | 6-8 هفته |
| **تأثیر بر سیستم** | 🔴 بسیار بالا — پایه تمام عملیات مالی |

### Module 5: Payment Gateway

| بُعد | تحلیل |
|---|---|
| **وضعیت فعلی** | ❌ وجود ندارد |
| **مشکلات** | عدم اتصال به درگاه‌های پرداخت |
| **ریسک‌ها** | بدون درگاه پرداخت، واریز/برداشت فیات غیرممکن |
| **اولویت اصلاح** | 🔴 P0 — بحرانی |
| **هزینه فنی** | متوسط-بالا (multiple PSP integrations) |
| **زمان پیاده‌سازی** | 4-8 هفته (per PSP) |
| **تأثیر بر سیستم** | 🔴 بسیار بالا |

### Module 6: FX Engine

| بُعد | تحلیل |
|---|---|
| **وضعیت فعلی** | ❌ وجود ندارد |
| **مشکلات** | عدم سیستم نرخ ارز |
| **ریسک‌ها** | بدون FX، تبدیل ارز غیرممکن |
| **اولویت اصلاح** | 🟡 P1 — مهم |
| **هزینه فنی** | متوسط |
| **زمان پیاده‌سازی** | 3-5 هفته |
| **تأثیر بر سیستم** | 🟡 بالا |

### Module 7: Exchange Engine

| بُعد | تحلیل |
|---|---|
| **وضعیت فعلی** | ❌ وجود ندارد |
| **مشکلات** | عدم سیستم صرافی |
| **ریسک‌ها** | بدون Exchange، تبدیل ارز دیجیتال غیرممکن |
| **اولویت اصلاح** | 🟡 P1 — مهم |
| **هزینه فنی** | بالا (matching engine, liquidity management) |
| **زمان پیاده‌سازی** | 8-12 هفته |
| **تأثیر بر سیستم** | 🟡 بالا |

### Module 8: Settlement Engine

| بُعد | تحلیل |
|---|---|
| **وضعیت فعلی** | ❌ وجود ندارد |
| **مشکلات** | عدم سیستم تسویه |
| **ریسک‌ها** | بدون Settlement، تسویه با شرکا و بانک‌ها غیرممکن |
| **اولویت اصلاح** | 🟡 P1 — مهم |
| **هزینه فنی** | بالا |
| **زمان پیاده‌سازی** | 6-10 هفته |
| **تأثیر بر سیستم** | 🟡 بالا |

### Module 9: KYC Service

| بُعد | تحلیل |
|---|---|
| **وضعیت فعلی** | ❌ وجود ندارد |
| **مشکلات** | عدم تأیید هویت کاربران |
| **ریسک‌ها** | عدم انطباق با مقررات بین‌المللی (KYC/AML) |
| **اولویت اصلاح** | 🔴 P0 — بحرانی |
| **هزینه فنی** | متوسط (third-party provider costs) |
| **زمان پیاده‌سازی** | 4-6 هفته |
| **تأثیر بر سیستم** | 🔴 بسیار بالا — بدون KYC، مجوز مالی دریافت نمی‌شود |

### Module 10: AML Engine

| بُعد | تحلیل |
|---|---|
| **وضعیت فعلی** | ❌ وجود ندارد |
| **مشکلات** | عدم نظارت بر تراکنش‌های مشکوک |
| **ریسک‌ها** | ریسک بالای پول‌شویی، جریمه‌های سنگین |
| **اولویت اصلاح** | 🔴 P0 — بحرانی |
| **هزینه فنی** | بالا (ML models, watchlist databases) |
| **زمان پیاده‌سازی** | 8-12 هفته |
| **تأثیر بر سیستم** | 🔴 بسیار بالا — الزام قانونی |

### Module 11: Risk Engine

| بُعد | تحلیل |
|---|---|
| **وضعیت فعلی** | ❌ وجود ندارد |
| **مشکلات** | عدم سیستم تشخیص تقلب و ریسک |
| **ریسک‌ها** | ریسک بالای تقلب و سرقت |
| **اولویت اصلاح** | 🟡 P1 — مهم |
| **هزینه فنی** | بالا (ML/AI development) |
| **زمان پیاده‌سازی** | 8-12 هفته |
| **تأثیر بر سیستم** | 🟡 بالا |

### Module 12: Notification Service

| بُعد | تحلیل |
|---|---|
| **وضعیت فعلی** | ❌ وجود ندارد |
| **مشکلات** | عدم اطلاع‌رسانی به کاربران |
| **ریسک‌ها** | تجربه کاربری ضعیف، عدم اطلاع‌رسانی تراکنش |
| **اولویت اصلاح** | 🟡 P1 — مهم |
| **هزینه فنی** | کم-متوسط |
| **زمان پیاده‌سازی** | 2-3 هفته |
| **تأثیر بر سیستم** | 🟠 متوسط |

### Module 13: Audit Service

| بُعد | تحلیل |
|---|---|
| **وضعیت فعلی** | ❌ وجود ندارد |
| **مشکلات** | عدم ثبت عملیات |
| **ریسک‌ها** | عدم انطباق، عدم ردیابی حوادث |
| **اولویت اصلاح** | 🔴 P0 — بحرانی |
| **هزینه فنی** | متوسط |
| **زمان پیاده‌سازی** | 3-4 هفته |
| **تأثیر بر سیستم** | 🔴 بسیار بالا |

### Module 14-28: سایر سرویس‌ها

| سرویس | اولویت | هزینه | زمان | تأثیر |
|---|---|---|---|---|
| Merchant Service | 🟠 P2 | متوسط | 4-6 هفته | متوسط |
| Card Service | 🟠 P2 | بالا | 8-12 هفته | بالا |
| Remittance | 🟠 P2 | بالا | 6-10 هفته | بالا |
| QR Payment | 🟠 P2 | متوسط | 4-6 هفته | متوسط |
| Travel Service | 🟢 P3 | متوسط | 4-6 هفته | کم |
| eSIM Service | 🟢 P3 | کم | 2-3 هفته | کم |
| Escrow | 🟠 P2 | متوسط | 4-6 هفته | متوسط |
| Loyalty/Cashback | 🟢 P3 | متوسط | 4-6 هفته | متوسط |
| Subscription | 🟢 P3 | کم | 3-4 هفته | کم |

---

# 13. Enterprise Architecture Roadmap نهایی

## 13.1 معماری فعلی (As-Is)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    AS-IS ARCHITECTURE                                     │
│                                                                          │
│  ┌─────────────────────────────────────┐                                │
│  │         GitHub Repository            │                                │
│  │                                     │                                │
│  │         main.md (empty)             │                                │
│  │                                     │                                │
│  │         No code, no infra,          │                                │
│  │         no architecture             │                                │
│  │                                     │                                │
│  └─────────────────────────────────────┘                                │
│                                                                          │
│  مزایای As-Is:                                                           │
│  ✅ Zero technical debt                                                  │
│  ✅ No legacy constraints                                                │
│  ✅ Clean slate for optimal architecture                                 │
│  ✅ Can adopt latest best practices from day one                         │
│                                                                          │
│  معایب As-Is:                                                            │
│  ❌ Everything needs to be built                                         │
│  ❌ Time to market: 6-12 months for MVP                                  │
│  ❌ Need to make all architectural decisions upfront                     │
│  ❌ No operational experience to guide decisions                         │
└─────────────────────────────────────────────────────────────────────────┘
```

## 13.2 معماری پیشنهادی (To-Be)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    TO-BE ARCHITECTURE                                     │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  CLIENTS                                                         │    │
│  │  ├── Mobile App (Flutter) — iOS + Android                        │    │
│  │  ├── Web App (Next.js 14 + React)                                │    │
│  │  ├── Admin Panel (Next.js + Refine)                              │    │
│  │  └── Partner API (REST + Webhook)                                │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                              │                                           │
│                              ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  EDGE LAYER                                                      │    │
│  │  ├── CDN (CloudFlare / AWS CloudFront)                          │    │
│  │  ├── WAF (Web Application Firewall)                              │    │
│  │  ├── DDoS Protection                                             │    │
│  │  └── SSL/TLS Termination                                         │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                              │                                           │
│                              ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  API GATEWAY (Kong)                                               │    │
│  │  ├── Authentication (JWT validation)                              │    │
│  │  ├── Rate Limiting (per user, per IP, per API key)               │    │
│  │  ├── Request Routing                                             │    │
│  │  ├── API Versioning (v1, v2)                                     │    │
│  │  ├── Request/Response Transformation                             │    │
│  │  └── Circuit Breaker                                             │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                              │                                           │
│                              ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  SERVICE MESH (Istio)                                             │    │
│  │                                                                  │    │
│  │  ┌─────────────────────────────────────────────────────────┐    │    │
│  │  │  CORE DOMAIN (Kubernetes Namespace: ecardo-core)         │    │    │
│  │  │  ├── identity-service (Go)                                │    │    │
│  │  │  ├── user-service (Go)                                    │    │    │
│  │  │  ├── wallet-service (Go)                                  │    │    │
│  │  │  ├── ledger-service (Go)                                  │    │    │
│  │  │  └── payment-gateway-service (Go)                         │    │    │
│  │  └─────────────────────────────────────────────────────────┘    │    │
│  │                                                                  │    │
│  │  ┌─────────────────────────────────────────────────────────┐    │    │
│  │  │  FINANCIAL DOMAIN (K8s Namespace: ecardo-financial)      │    │    │
│  │  │  ├── fx-engine (Go)                                       │    │    │
│  │  │  ├── exchange-engine (Go)                                 │    │    │
│  │  │  ├── settlement-engine (Go)                               │    │    │
│  │  │  └── remittance-service (Go)                              │    │    │
│  │  └─────────────────────────────────────────────────────────┘    │    │
│  │                                                                  │    │
│  │  ┌─────────────────────────────────────────────────────────┐    │    │
│  │  │  COMPLIANCE DOMAIN (K8s Namespace: ecardo-compliance)    │    │    │
│  │  │  ├── kyc-service (Python/FastAPI)                         │    │    │
│  │  │  ├── aml-engine (Python/FastAPI)                          │    │    │
│  │  │  ├── kyt-engine (Python/FastAPI)                          │    │    │
│  │  │  ├── risk-engine (Python/FastAPI)                         │    │    │
│  │  │  └── compliance-service (Go)                              │    │    │
│  │  └─────────────────────────────────────────────────────────┘    │    │
│  │                                                                  │    │
│  │  ┌─────────────────────────────────────────────────────────┐    │    │
│  │  │  OPERATIONS DOMAIN (K8s Namespace: ecardo-ops)           │    │    │
│  │  │  ├── notification-service (Node.js)                      │    │    │
│  │  │  ├── audit-service (Go)                                   │    │    │
│  │  │  ├── card-service (Go)                                    │    │    │
│  │  │  ├── merchant-service (Go)                                │    │    │
│  │  │  └── scheduler-service (Go)                               │    │    │
│  │  └─────────────────────────────────────────────────────────┘    │    │
│  │                                                                  │    │
│  │  ┌─────────────────────────────────────────────────────────┐    │    │
│  │  │  EXTENSIONS DOMAIN (K8s Namespace: ecardo-extensions)    │    │    │
│  │  │  ├── travel-service (Node.js)                            │    │    │
│  │  │  ├── esim-service (Node.js)                               │    │    │
│  │  │  ├── qr-payment-service (Go)                              │    │    │
│  │  │  ├── escrow-service (Go)                                  │    │    │
│  │  │  ├── loyalty-service (Node.js)                            │    │    │
│  │  │  └── subscription-service (Node.js)                       │    │    │
│  │  └─────────────────────────────────────────────────────────┘    │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                              │                                           │
│                              ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  EVENT LAYER                                                     │    │
│  │  ├── Apache Kafka (primary event bus)                            │    │
│  │  │   ├── ecardo-events topic group                               │    │
│  │  │   ├── ecardo-financial-events topic group                     │    │
│  │  │   ├── ecardo-audit-events topic group                         │    │
│  │  │   └── ecardo-notifications topic group                        │    │
│  │  └── RabbitMQ (task queue for async jobs)                        │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                              │                                           │
│                              ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  DATA LAYER                                                      │    │
│  │  ├── PostgreSQL 16 (primary OLTP)                                │    │
│  │  │   ├── ecardo_identity database                                │    │
│  │  │   ├── ecardo_wallet database                                  │    │
│  │  │   ├── ecardo_ledger database                                  │    │
│  │  │   ├── ecardo_exchange database                                │    │
│  │  │   ├── ecardo_compliance database                              │    │
│  │  │   └── ecardo_operations database                              │    │
│  │  ├── Redis Cluster (cache + session + rate limiting)             │    │
│  │  ├── MongoDB (KYC documents, audit logs)                         │    │
│  │  ├── Elasticsearch (search, log aggregation)                     │    │
│  │  ├── TimescaleDB (market data, rate history)                     │    │
│  │  └── MinIO / S3 (file storage)                                   │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                              │                                           │
│                              ▼                                           │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  INFRASTRUCTURE LAYER                                            │    │
│  │  ├── Kubernetes (EKS/GKE) — Container Orchestration             │    │
│  │  ├── HashiCorp Vault — Secret Management                        │    │
│  │  ├── Terraform — Infrastructure as Code                          │    │
│  │  ├── Helm Charts — Kubernetes Package Management                │    │
│  │  ├── ArgoCD — GitOps Continuous Delivery                         │    │
│  │  ├── Prometheus + Grafana — Metrics & Dashboards                │    │
│  │  ├── Jaeger — Distributed Tracing                                │    │
│  │  ├── Loki — Log Aggregation                                      │    │
│  │  └── PagerDuty — Incident Management                            │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
```

## 13.3 نقشه مهاجرت مرحله‌ای (Phased Migration Roadmap)

### Phase 0: Foundation (هفته 1-4) — "Set the Stage"

```
┌─────────────────────────────────────────────────────────────────────────┐
│  PHASE 0: FOUNDATION                                                     │
│  Duration: 4 weeks | Team: 2-3 engineers | Priority: CRITICAL           │
│                                                                          │
│  Goals:                                                                  │
│  ├── Set up development environment                                      │
│  ├── Initialize monorepo structure                                       │
│  ├── Set up CI/CD pipeline                                               │
│  ├── Set up containerization (Docker)                                    │
│  ├── Set up local Kubernetes (minikube/k3s)                              │
│  ├── Set up database (PostgreSQL)                                        │
│  ├── Set up message broker (Kafka/RabbitMQ)                              │
│  ├── Set up Redis                                                       │
│  ├── Set up Vault (basic)                                                │
│  ├── Set up monitoring (Prometheus + Grafana)                            │
│  ├── Set up logging (ELK/Loki)                                          │
│  ├── Define coding standards & conventions                               │
│  ├── Set up API documentation (OpenAPI/Swagger)                          │
│  └── Architecture Decision Records (ADR) template                       │
│                                                                          │
│  Deliverables:                                                           │
│  ├── Working development environment                                     │
│  ├── CI/CD pipeline (build → test → deploy to staging)                  │
│  ├── Base project structure (monorepo with service scaffolding)          │
│  ├── Database migrations framework                                       │
│  ├── Shared libraries (error handling, logging, validation)              │
│  └── Infrastructure as Code (Terraform for staging)                      │
│                                                                          │
│  Cost Estimate: Infrastructure ~$200-500/month (staging)                 │
└─────────────────────────────────────────────────────────────────────────┘
```

### Phase 1: Core Identity & Wallet (هفته 5-14) — "The Heart"

```
┌─────────────────────────────────────────────────────────────────────────┐
│  PHASE 1: CORE IDENTITY & WALLET                                         │
│  Duration: 10 weeks | Team: 4-6 engineers | Priority: CRITICAL          │
│                                                                          │
│  Weeks 5-8: Identity Service                                             │
│  ├── User registration (email, phone)                                    │
│  ├── Login with password                                                 │
│  ├── MFA (TOTP)                                                          │
│  ├── JWT + Refresh token                                                 │
│  ├── RBAC (admin, user, support roles)                                  │
│  ├── Password reset flow                                                 │
│  └── Device fingerprinting (basic)                                       │
│                                                                          │
│  Weeks 6-10: User Service + KYC                                          │
│  ├── User profile management                                             │
│  ├── KYC Level 0 (email verified)                                        │
│  ├── KYC Level 1 (phone verified + basic info)                          │
│  ├── KYC Level 2 (ID verification — integrate SumSub/Onfido)            │
│  ├── Document upload & storage                                           │
│  └── User limits per KYC level                                           │
│                                                                          │
│  Weeks 7-12: Wallet Service                                              │
│  ├── Fiat wallet creation (IRR first)                                    │
│  ├── Crypto wallet creation (USDT TRC20 first)                           │
│  ├── HD wallet integration (for crypto address generation)               │
│  ├── Deposit flow (fiat via bank, crypto via blockchain monitoring)     │
│  ├── Internal transfer (P2P)                                             │
│  └── Balance query (computed from Ledger)                                │
│                                                                          │
│  Weeks 8-14: Ledger Service                                              │
│  ├── Double-entry journal system                                         │
│  ├── Chart of accounts                                                   │
│  ├── Journal entry creation & validation                                 │
│  ├── Balance computation (aggregate from journal lines)                  │
│  ├── Balance snapshot caching                                            │
│  ├── Reconciliation framework (basic)                                    │
│  └── Audit trail for all entries                                         │
│                                                                          │
│  Weeks 10-14: Notification Service (basic)                               │
│  ├── Email notifications (transaction, security)                         │
│  ├── SMS OTP                                                             │
│  └── Push notification (FCM) — basic                                    │
│                                                                          │
│  Weeks 10-14: Audit Service (basic)                                      │
│  ├── Authentication event logging                                        │
│  ├── Transaction logging                                                 │
│  └── Admin action logging                                                │
│                                                                          │
│  Week 14: Integration Testing & Security Audit                           │
│  ├── End-to-end testing of all flows                                     │
│  ├── Security penetration testing                                        │
│  ├── Performance baseline testing                                        │
│  └── Bug fixes and hardening                                             │
│                                                                          │
│  Deliverables:                                                           │
│  ├── Working authentication system                                       │
│  ├── KYC Level 1-2 functional                                            │
│  ├── IRR wallet with deposit (via bank transfer)                        │
│  ├── USDT TRC20 wallet with deposit (via blockchain)                    │
│  ├── Double-entry Ledger fully functional                                │
│  ├── Basic P2P transfer                                                  │
│  ├── Notification system (email, SMS, push)                             │
│  └── Audit logging                                                       │
│                                                                          │
│  Milestone: MVP — User can register, verify identity, deposit IRR/USDT, │
│             view balance, and transfer to another user                   │
│                                                                          │
│  Cost Estimate: Infrastructure ~$500-1,500/month + KYC provider costs   │
└─────────────────────────────────────────────────────────────────────────┘
```

### Phase 2: Exchange & Payments (هفته 15-26) — "The Engine"

```
┌─────────────────────────────────────────────────────────────────────────┐
│  PHASE 2: EXCHANGE & PAYMENTS                                            │
│  Duration: 12 weeks | Team: 6-8 engineers | Priority: HIGH              │
│                                                                          │
│  Weeks 15-18: FX Engine                                                  │
│  ├── Rate aggregation from multiple sources                              │
│  ├── Real-time rate calculation                                          │
│  ├── Spread configuration                                                │
│  ├── Rate caching & publishing                                           │
│  └── Cross-rate calculation                                              │
│                                                                          │
│  Weeks 15-20: Exchange Engine                                            │
│  ├── Fiat ⇄ Fiat (IRR ↔ USD, EUR first)                                │
│  ├── Fiat ⇄ Crypto (USD ↔ USDT)                                        │
│  ├── Crypto ⇄ Crypto (USDT ↔ BTC)                                      │
│  ├── Quote → Lock → Execute flow                                         │
│  ├── Fee engine (tiered)                                                 │
│  ├── Exchange history                                                    │
│  └── External exchange integration (Binance API)                         │
│                                                                          │
│  Weeks 16-20: Payment Gateway                                            │
│  ├── PSP integration (1 primary PSP)                                     │
│  ├── Card deposit (fiat)                                                 │
│  ├── Bank transfer deposit (fiat)                                        │
│  ├── Withdrawal to bank                                                  │
│  ├── Payment routing (basic)                                             │
│  └── Webhook handling                                                    │
│                                                                          │
│  Weeks 18-22: Additional Wallets                                         │
│  ├── USD wallet                                                          │
│  ├── EUR wallet                                                          │
│  ├── USDT ERC20 wallet                                                   │
│  ├── BTC wallet                                                          │
│  ├── ETH wallet                                                          │
│  └── Withdrawal flow (fiat + crypto)                                     │
│                                                                          │
│  Weeks 20-24: AML Engine (basic)                                         │
│  ├── Transaction monitoring (rule-based)                                 │
│  ├── Velocity checks                                                     │
│  ├── Watchlist screening (basic)                                         │
│  ├── Alert generation                                                    │
│  └── Manual review workflow                                              │
│                                                                          │
│  Weeks 22-26: Settlement Engine (basic)                                  │
│  ├── Internal settlement (wallet ↔ wallet)                              │
│  ├── Bank settlement (manual batch)                                      │
│  ├── PSP settlement                                                      │
│  └── Reconciliation (basic)                                              │
│                                                                          │
│  Weeks 24-26: Risk Engine (basic)                                        │
│  ├── User risk scoring                                                   │
│  ├── Transaction risk assessment                                         │
│  └── Withdrawal approval engine                                          │
│                                                                          │
│  Milestone: Full exchange capability, fiat on/off ramp,                  │
│             multi-currency wallets, basic AML                            │
│                                                                          │
│  Cost Estimate: Infrastructure ~$1,500-3,000/month                       │
│                 + Exchange API costs + PSP fees + KYC/AML provider costs │
└─────────────────────────────────────────────────────────────────────────┘
```

### Phase 3: Scale & Compliance (هفته 27-40) — "The Shield"

```
┌─────────────────────────────────────────────────────────────────────────┐
│  PHASE 3: SCALE & COMPLIANCE                                             │
│  Duration: 14 weeks | Team: 8-10 engineers | Priority: HIGH             │
│                                                                          │
│  Weeks 27-30: Microservice Extraction                                    │
│  ├── Extract Identity Service                                            │
│  ├── Extract Wallet Service                                              │
│  ├── Extract Ledger Service                                              │
│  ├── API Gateway setup (Kong)                                            │
│  └── Service Mesh (Istio) — basic                                       │
│                                                                          │
│  Weeks 27-32: Security Hardening                                         │
│  ├── HSM integration (Cloud HSM)                                         │
│  ├── Multi-signature wallets (Hot/Warm/Cold)                            │
│  ├── Key rotation automation                                             │
│  ├── Penetration testing (external firm)                                 │
│  ├── PCI-DSS compliance preparation                                      │
│  ├── GDPR compliance                                                     │
│  └── Security monitoring (SIEM)                                          │
│                                                                          │
│  Weeks 28-34: Advanced AML/KYC                                           │
│  ├── ML-based anomaly detection                                          │
│  ├── Advanced watchlist screening (OFAC, EU, UN)                         │
│  ├── KYT (Know Your Transaction) for crypto                             │
│  ├── SAR/CTR reporting                                                   │
│  ├── Case management system                                              │
│  └── Regulatory reporting automation                                     │
│                                                                          │
│  Weeks 30-36: Card Service                                               │
│  ├── Virtual card issuance (Marqeta/Stripe Issuing integration)          │
│  ├── Card management (activate, block, limit)                           │
│  ├── Authorization webhook                                               │
│  └── Spending controls                                                   │
│                                                                          │
│  Weeks 32-38: Merchant Service                                           │
│  ├── Merchant onboarding                                                 │
│  ├── Payment links                                                       │
│  ├── Invoice system                                                      │
│  ├── Merchant dashboard                                                  │
│  └── Settlement configuration                                            │
│                                                                          │
│  Weeks 34-40: Infrastructure Scale                                       │
│  ├── Multi-AZ Kubernetes deployment                                      │
│  ├── Auto-scaling configuration                                          │
│  ├── Database read replicas                                              │
│  ├── CDN integration                                                     │
│  ├── Load testing & optimization                                         │
│  └── Disaster recovery testing                                           │
│                                                                          │
│  Milestone: Production-grade platform with compliance,                   │
│             card service, merchant service, scaled infrastructure        │
│                                                                          │
│  Cost Estimate: Infrastructure ~$3,000-8,000/month                       │
│                 + Compliance costs + Card issuer fees                     │
└─────────────────────────────────────────────────────────────────────────┘
```

### Phase 4: Growth & Expansion (هفته 41-56) — "The Horizon"

```
┌─────────────────────────────────────────────────────────────────────────┐
│  PHASE 4: GROWTH & EXPANSION                                             │
│  Duration: 16 weeks | Team: 10-15 engineers | Priority: MEDIUM          │
│                                                                          │
│  Weeks 41-46: Remittance Service                                         │
│  ├── Corridor setup (top 10 corridors)                                   │
│  ├── Provider integration (Wise, Remitly API)                           │
│  ├── Fee comparison engine                                               │
│  ├── Delivery tracking                                                   │
│  └── Compliance per corridor                                             │
│                                                                          │
│  Weeks 41-48: QR Payment + China Payments                                │
│  ├── QR code generation & scanning                                      │
│  ├── EMVCo QR standard                                                   │
│  ├── Alipay integration                                                  │
│  ├── WeChat Pay integration                                              │
│  ├── UnionPay integration                                                │
│  └── Merchant QR payment                                                 │
│                                                                          │
│  Weeks 43-48: Mobile App                                                 │
│  ├── Flutter app (iOS + Android)                                         │
│  ├── Core features (wallet, exchange, transfer)                          │
│  ├── Biometric authentication                                            │
│  ├── Push notifications                                                  │
│  └── Card management                                                     │
│                                                                          │
│  Weeks 46-52: Travel & Lifestyle                                         │
│  ├── eSIM service (Airalo integration)                                   │
│  ├── Mobile recharge (Reloadly integration)                              │
│  ├── Hotel booking (Booking/HotelBeds API)                               │
│  ├── Travel insurance                                                    │
│  └── Airport services                                                    │
│                                                                          │
│  Weeks 48-54: Advanced Features                                          │
│  ├── Escrow service                                                      │
│  ├── Subscription billing                                                │
│  ├── Cashback engine                                                     │
│  ├── Loyalty program                                                     │
│  ├── Referral system                                                     │
│  └── Advanced analytics dashboard                                        │
│                                                                          │
│  Weeks 52-56: Multi-Region & DR                                          │
│  ├── Secondary region deployment                                         │
│  ├── Cross-region replication                                            │
│  ├── DR drill                                                            │
│  ├── Performance optimization                                            │
│  └── Load testing at scale                                               │
│                                                                          │
│  Milestone: Full-featured international fintech platform                 │
│                                                                          │
│  Cost Estimate: Infrastructure ~$8,000-20,000/month                      │
│                 + Multiple provider/API costs                             │
└─────────────────────────────────────────────────────────────────────────┘
```

## 13.4 Timeline Visualization

```
هفته:  1    4    8    12   16   20   24   28   32   36   40   44   48   52   56
       │    │    │    │    │    │    │    │    │    │    │    │    │    │    │
Phase 0 ████                                                                    
Foundation                                                                      
       │    │    │    │    │    │    │    │    │    │    │    │    │    │    │
Phase 1 ░░░░░░░░░░░░░░░░░░░░                                                  
Core Identity & Wallet                                                          
       │    │    │    │    │    │    │    │    │    │    │    │    │    │    │
Phase 2                ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░                        
Exchange & Payments                                                             
       │    │    │    │    │    │    │    │    │    │    │    │    │    │    │
Phase 3                                   ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░  
Scale & Compliance                                                              
       │    │    │    │    │    │    │    │    │    │    │    │    │    │    │
Phase 4                                                      ░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░
Growth & Expansion                                                              
       │    │    │    │    │    │    │    │    │    │    │    │    │    │    │
MVP ✅            │                    │                    │                    
       │    │    │    │    │    │    │    │    │    │    │    │    │    │    │
                 V1.0               V2.0               V3.0                     
                 Launch             Launch             Launch                    
```

## 13.5 اولویت‌بندی اصلاحات

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    PRIORITIZATION MATRIX                                  │
│                                                                          │
│  P0 — بحرانی (Must Have for Launch):                                    │
│  ├── Identity Service (Auth, MFA, RBAC)                                  │
│  ├── User Service (Profile, KYC L1-L2)                                   │
│  ├── Wallet Service (Fiat + Crypto)                                      │
│  ├── Ledger Service (Double-Entry)                                       │
│  ├── Payment Gateway (1 PSP)                                             │
│  ├── Notification Service (Email, SMS)                                   │
│  └── Audit Service (Immutable Log)                                       │
│                                                                          │
│  P1 — مهم (Must Have for V1.0):                                         │
│  ├── FX Engine                                                           │
│  ├── Exchange Engine                                                     │
│  ├── Settlement Engine (basic)                                           │
│  ├── AML Engine (rule-based)                                             │
│  ├── Risk Engine (basic)                                                 │
│  ├── Withdrawal Approval Engine                                          │
│  └── KYC Level 3                                                         │
│                                                                          │
│  P2 — ارزشمند (Should Have for V2.0):                                   │
│  ├── Card Service (Virtual)                                              │
│  ├── Merchant Service                                                    │
│  ├── QR Payment Service                                                  │
│  ├── Remittance Service                                                  │
│  ├── Escrow Service                                                      │
│  ├── Advanced AML (ML-based)                                             │
│  └── Multiple PSP Integration                                            │
│                                                                          │
│  P3 — آینده (Nice to Have for V3.0):                                    │
│  ├── Travel Service                                                      │
│  ├── eSIM Service                                                        │
│  ├── Loyalty/Cashback                                                    │
│  ├── Subscription Billing                                                │
│  ├── China Payments (Alipay, WeChat)                                     │
│  ├── Physical Card                                                       │
│  └── Multi-Region Deployment                                             │
└─────────────────────────────────────────────────────────────────────────┘
```

## 13.6 تحلیل ریسک‌ها

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    RISK ANALYSIS                                          │
│                                                                          │
│  ┌──────────────────────┬────────────┬────────────┬──────────────────┐  │
│  │ Risk                  │ Probability│ Impact     │ Mitigation        │  │
│  ├──────────────────────┼────────────┼────────────┼──────────────────┤  │
│  │ Wrong technology      │ Medium     │ Very High  │ POC before       │  │
│  │ choices               │            │            │ commitment       │  │
│  ├──────────────────────┼────────────┼────────────┼──────────────────┤  │
│  │ Security breach       │ Medium     │ Critical   │ Security-first   │  │
│  │                       │            │            │ design, audit    │  │
│  ├──────────────────────┼────────────┼────────────┼──────────────────┤  │
│  │ Ledger inconsistency  │ Low        │ Critical   │ Double-entry,    │  │
│  │                       │            │            │ reconciliation   │  │
│  ├──────────────────────┼────────────┼────────────┼──────────────────┤  │
│  │ Exchange partner      │ Medium     │ High       │ Multi-partner    │  │
│  │ disruption            │            │            │ strategy         │  │
│  ├──────────────────────┼────────────┼────────────┼──────────────────┤  │
│  │ Regulatory changes    │ Medium     │ Very High  │ Compliance-first │  │
│  │                       │            │            │ modular design   │  │
│  ├──────────────────────┼────────────┼────────────┼──────────────────┤  │
│  │ Key person            │ High       │ High       │ Documentation,   │  │
│  │ dependency            │            │            │ knowledge share  │  │
│  ├──────────────────────┼────────────┼────────────┼──────────────────┤  │
│  │ Scope creep           │ High       │ High       │ Phased approach, │  │
│  │                       │            │            │ strict MVP       │  │
│  ├──────────────────────┼────────────┼────────────┼──────────────────┤  │
│  │ Fund theft/hack       │ Low        │ Critical   │ Multi-sig, HSM,  │  │
│  │                       │            │            │ cold storage     │  │
│  ├──────────────────────┼────────────┼────────────┼──────────────────┤  │
│  │ Data loss             │ Low        │ Critical   │ Multi-region     │  │
│  │                       │            │            │ backup, DR       │  │
│  ├──────────────────────┼────────────┼────────────┼──────────────────┤  │
│  │ Performance at scale  │ Medium     │ High       │ Load testing,    │  │
│  │                       │            │            │ auto-scaling     │  │
│  ├──────────────────────┼────────────┼────────────┼──────────────────┤  │
│  │ Cloud provider lock-  │ Medium     │ Medium     │ Multi-cloud,     │  │
│  │ in                    │            │            │ K8s abstraction  │  │
│  └──────────────────────┴────────────┴────────────┴──────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
```

## 13.7 پیشنهادهای امنیتی

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    SECURITY RECOMMENDATIONS                               │
│                                                                          │
│  1. Security-by-Design:                                                  │
│     ├── Threat modeling before each phase                                │
│     ├── Security review for every PR affecting financial flows           │
│     ├── Separate security review for wallet operations                   │
│     └── Regular penetration testing (quarterly)                          │
│                                                                          │
│  2. Defense in Depth:                                                    │
│     ├── WAF → API Gateway → Service Mesh → Application → Database       │
│     ├── Each layer independently secured                                 │
│     └── No single point of security failure                              │
│                                                                          │
│  3. Zero Trust Architecture:                                             │
│     ├── Every service-to-service call authenticated (mTLS)              │
│     ├── Every request authorized (RBAC + ABAC)                          │
│     ├── No implicit trust between services                               │
│     └── Least privilege access everywhere                                │
│                                                                          │
│  4. Cryptographic Standards:                                             │
│     ├── AES-256-GCM for data encryption                                 │
│     ├── Argon2id for password hashing                                    │
│     ├── Ed25519 or secp256k1 for wallet keys                            │
│     ├── TLS 1.3 minimum for transit                                      │
│     └── HSM for critical key storage (Phase 3+)                         │
│                                                                          │
│  5. Compliance Framework:                                                │
│     ├── PCI-DSS Level 1 (for card processing)                           │
│     ├── GDPR (for EU users)                                              │
│     ├── SOC 2 Type II (aspirational)                                     │
│     ├── Local financial regulations per jurisdiction                     │
│     └── Regular compliance audits                                        │
│                                                                          │
│  6. Incident Response:                                                   │
│     ├── 24/7 security monitoring (SOC)                                   │
│     ├── Automated alerting on suspicious activities                     │
│     ├── Incident response playbook                                       │
│     ├── Regular DR drills                                                │
│     └── Bug bounty program (post-launch)                                 │
│                                                                          │
│  7. Wallet-Specific Security:                                            │
│     ├── Multi-signature for all operational wallets                     │
│     ├── Hot/Warm/Cold distribution                                       │
│     ├── Withdrawal velocity limits                                       │
│     ├── Address whitelisting for large withdrawals                      │
│     ├── Time-locked transactions for cold wallet access                 │
│     └── Real-time monitoring of blockchain transactions                 │
└─────────────────────────────────────────────────────────────────────────┘
```

## 13.8 پیشنهادهای افزایش کارایی

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    PERFORMANCE RECOMMENDATIONS                            │
│                                                                          │
│  1. Database Optimization:                                               │
│     ├── Proper indexing strategy from day one                            │
│     ├── Read replicas for query-heavy services                          │
│     ├── Connection pooling (PgBouncer)                                   │
│     ├── Materialized views for balance snapshots                        │
│     ├── Table partitioning for journal_lines (monthly)                  │
│     ├── Query optimization and EXPLAIN ANALYZE review                   │
│     └── Archive old data to cold storage (>1 year)                      │
│                                                                          │
│  2. Caching Strategy:                                                    │
│     ├── L1 Cache: In-memory (Go sync.Map / Java Caffeine)              │
│     ├── L2 Cache: Redis Cluster                                          │
│     ├── Cache-aside pattern for user profiles                           │
│     ├── Write-through for wallet balances (snapshot)                    │
│     ├── Rate caching: Redis with TTL (10-60 sec)                        │
│     └── Cache invalidation: event-driven                                │
│                                                                          │
│  3. Async Processing:                                                    │
│     ├── All non-critical operations via message queue                    │
│     ├── Notifications: async via Kafka/RabbitMQ                         │
│     ├── Audit logging: async via Kafka                                  │
│     ├── AML checks: async (flag, don't block)                           │
│     ├── Reconciliation: scheduled batch jobs                             │
│     └── Report generation: async with callback                          │
│                                                                          │
│  4. API Optimization:                                                    │
│     ├── Response compression (gzip/brotli)                              │
│     ├── Pagination for list endpoints                                    │
│     ├── Field selection (GraphQL-style sparse fieldsets)                │
│     ├── Edge caching for rate data                                       │
│     ├── Request deduplication                                            │
│     └── Connection keep-alive                                            │
│                                                                          │
│  5. Scalability:                                                         │
│     ├── Stateless services (horizontal scaling)                         │
│     ├── Auto-scaling based on CPU/memory/request rate                   │
│     ├── Database sharding strategy (future: by user_id)                 │
│     ├── Event sourcing for audit-heavy services                          │
│     ├── CQRS for read-heavy services                                     │
│     └── CDN for static assets and rate data                             │
└─────────────────────────────────────────────────────────────────────────┘
```

## 13.9 پیشنهادهای افزایش مقیاس‌پذیری

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    SCALABILITY RECOMMENDATIONS                            │
│                                                                          │
│  1. Application Layer:                                                   │
│     ├── Stateless services → scale horizontally with K8s HPA            │
│     ├── Design for 1000 → 10,000 → 100,000 → 1,000,000 users          │
│     ├── Each service independently scalable                              │
│     └── Resource limits and requests properly configured                │
│                                                                          │
│  2. Data Layer:                                                          │
│     ├── Phase 1: Single PostgreSQL (up to ~10K users)                   │
│     ├── Phase 2: Read replicas + PgBouncer (up to ~100K users)         │
│     ├── Phase 3: Horizontal sharding by user_id (1M+ users)            │
│     ├── Phase 4: CQRS — separate read/write models                      │
│     └── Event Sourcing for Ledger (append-only, highly scalable)       │
│                                                                          │
│  3. Message Layer:                                                       │
│     ├── Kafka partitioning for parallel consumption                     │
│     ├── Topic per domain (financial, audit, notification)               │
│     ├── Consumer group scaling                                           │
│     └── Dead letter queues for failed messages                          │
│                                                                          │
│  4. Infrastructure Layer:                                                │
│     ├── Multi-AZ deployment (availability)                               │
│     ├── Multi-region deployment (latency + DR)                          │
│     ├── CDN for global users                                             │
│     ├── Edge computing for rate data (CloudFlare Workers)               │
│     └── Auto-scaling with predictive scaling for known patterns         │
│                                                                          │
│  5. Geographic Scaling:                                                  │
│     ├── Start with 1 region (closest to primary user base)              │
│     ├── Add region when user base reaches threshold                     │
│     ├── Data residency requirements per jurisdiction                    │
│     └── Global load balancing with latency-based routing                │
└─────────────────────────────────────────────────────────────────────────┘
```

## 13.10 پیشنهادهای کاهش وابستگی به سرویس‌دهندگان خارجی

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    EXTERNAL DEPENDENCY REDUCTION                          │
│                                                                          │
│  1. Exchange Providers:                                                  │
│     ├── Always maintain 3+ active exchange integrations                 │
│     ├── Abstract exchange interface (swap providers easily)             │
│     ├── Use DEX as decentralized fallback                                │
│     ├── Build internal matching engine for P2P trades                   │
│     ├── OTC desk for large trades (avoid exchange dependency)           │
│     └── Self-hosted blockchain nodes (don't rely on Infura only)        │
│                                                                          │
│  2. Banking Partners:                                                    │
│     ├── Multiple bank accounts (3+ banks minimum)                       │
│     ├── Accounts in multiple jurisdictions                               │
│     ├── Direct SWIFT integration (not through intermediary)             │
│     ├── Open Banking API integration where available                    │
│     └── Crypto-to-fiat bridge as alternative to bank transfers          │
│                                                                          │
│  3. Payment Providers:                                                   │
│     ├── Abstract payment provider interface                              │
│     ├── Always maintain 2+ active PSP integrations                      │
│     ├── Direct bank integration as last resort                           │
│     ├── Crypto payment as alternative                                    │
│     └── Payment routing engine (auto-failover)                          │
│                                                                          │
│  4. KYC/AML Providers:                                                   │
│     ├── Abstract provider interface                                      │
│     ├── 2+ KYC providers (SumSub + Onfido, etc.)                       │
│     ├── In-house document verification (ML model) as backup             │
│     ├── Manual review capability (always available)                     │
│     └── Open-source watchlist databases as supplement                   │
│                                                                          │
│  5. Infrastructure:                                                      │
│     ├── Kubernetes (cloud-agnostic orchestration)                       │
│     ├── Terraform (infrastructure as code, portable)                    │
│     ├── Container-based deployment (runs anywhere)                      │
│     ├── Avoid proprietary services where possible                       │
│     ├── Self-hosted alternatives for critical services                  │
│     │   (MinIO instead of S3, Vault instead of AWS Secrets Manager)    │
│     └── Multi-cloud capability from day one                             │
│                                                                          │
│  6. Blockchain Infrastructure:                                           │
│     ├── Self-hosted full nodes (BTC, ETH, TRON)                        │
│     ├── Multiple RPC providers (Infura + Alchemy + self-hosted)        │
│     ├── Blockchain monitoring (self-hosted block explorer)              │
│     └── HD wallet management (self-hosted, no third-party custody)     │
│                                                                          │
│  7. Communication Providers:                                             │
│     ├── SMS: 2+ providers (Twilio + local provider)                    │
│     ├── Email: AWS SES + SendGrid (backup)                              │
│     ├── Push: FCM + APNs + WebSocket fallback                          │
│     └── Abstract communication interface                                 │
└─────────────────────────────────────────────────────────────────────────┘
```

## 13.11 پیشنهادهای آماده‌سازی برای مجوزهای مالی

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    FINANCIAL LICENSE READINESS                             │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  1. TECHNICAL REQUIREMENTS FOR LICENSES                          │    │
│  │                                                                  │    │
│  │  PCI-DSS Compliance:                                             │    │
│  │  ├── Card data never stored in plaintext                         │    │
│  │  ├── Tokenization for card numbers                               │    │
│  │  ├── Network segmentation (card processing isolated)             │    │
│  │  ├── Quarterly vulnerability scans                               │    │
│  │  ├── Annual penetration testing                                  │    │
│  │  ├── Access control and logging                                  │    │
│  │  └── Encryption at rest and in transit                           │    │
│  │                                                                  │    │
│  │  AML/CFT Compliance:                                             │    │
│  │  ├── Customer Due Diligence (CDD)                                │    │
│  │  ├── Enhanced Due Diligence (EDD) for high-risk customers        │    │
│  │  ├── Transaction monitoring (real-time)                          │    │
│  │  ├── Suspicious Activity Reporting (SAR)                         │    │
│  │  ├── Currency Transaction Reporting (CTR)                        │    │
│  │  ├── Sanctions screening (OFAC, EU, UN, local)                   │    │
│  │  ├── PEP screening                                               │    │
│  │  ├── Record keeping (5-7 years)                                  │    │
│  │  └── Staff training records                                      │    │
│  │                                                                  │    │
│  │  Data Protection:                                                │    │
│  │  ├── GDPR compliance (EU users)                                  │    │
│  │  ├── Data minimization                                           │    │
│  │  ├── Right to erasure capability                                 │    │
│  │  ├── Data portability                                            │    │
│  │  ├── Privacy impact assessment                                   │    │
│  │  ├── Data Processing Agreement (DPA) with all vendors            │    │
│  │  └── Cross-border data transfer mechanisms                       │    │
│  │                                                                  │    │
│  │  Operational Requirements:                                       │    │
│  │  ├── Business continuity plan                                    │    │
│  │  ├── Disaster recovery plan with tested RPO/RTO                  │    │
│  │  ├── Incident response plan                                      │    │
│  │  ├── Change management process                                   │    │
│  │  ├── Access control policy                                       │    │
│  │  ├── Vendor management policy                                    │    │
│  │  └── Regular internal audits                                     │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  2. BANKING PARTNERSHIP READINESS                                │    │
│  │                                                                  │    │
│  │  Technical Integration:                                          │    │
│  │  ├── SWIFT connectivity (MT103, MT202)                           │    │
│  │  ├── ISO 20022 message format support                            │    │
│  │  ├── Open Banking API integration                                │    │
│  │  ├── Real-time payment system integration (where available)      │    │
│  │  ├── Nostro/Vostro account management                            │    │
│  │  └── Automated reconciliation with bank statements               │    │
│  │                                                                  │    │
│  │  Compliance Demonstration:                                       │    │
│  │  ├── Audit-ready transaction records                              │    │
│  │  ├── Real-time compliance reporting capability                   │    │
│  │  ├── Customer risk profiling                                     │    │
│  │  ├── Transaction monitoring and alerting                         │    │
│  │  └── Sanctions screening evidence                                │    │
│  │                                                                  │    │
│  │  Operational Excellence:                                         │    │
│  │  ├── SLA monitoring and reporting                                │    │
│  │  ├── 99.9%+ uptime demonstrated                                  │    │
│  │  ├── Incident response within agreed timelines                   │    │
│  │  ├── Regular security assessments                                │    │
│  │  └── Insurance coverage (cyber, professional liability)          │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  3. INTERNATIONAL PARTNER READINESS                              │    │
│  │                                                                  │    │
│  │  Multi-Jurisdictional Capability:                                │    │
│  │  ├── Configurable compliance rules per jurisdiction              │    │
│  │  ├── Multi-currency accounting                                   │    │
│  │  ├── Multi-language support (EN, FA, AR, ZH, RU, TR)           │    │
│  │  ├── Multi-timezone support                                      │    │
│  │  ├── Tax reporting per jurisdiction                              │    │
│  │  └── Regulatory change management process                        │    │
│  │                                                                  │    │
│  │  API-First Architecture:                                         │    │
│  │  ├── Well-documented RESTful APIs                                │    │
│  │  ├── Webhook integration for real-time events                    │    │
│  │  ├── Sandbox environment for partners                            │    │
│  │  ├── Rate limiting and SLA management                            │    │
│  │  └── SDK/client libraries for popular languages                  │    │
│  │                                                                  │    │
│  │  Trust & Transparency:                                           │    │
│  │  ├── SOC 2 Type II certification path                            │    │
│  │  ├── ISO 27001 certification path                                │    │
│  │  ├── Regular third-party security audits                         │    │
│  │  ├── Bug bounty program                                          │    │
│  │  └── Transparency reports                                        │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 13.12 خلاصه نهایی — خروجی‌های کلیدی

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    EXECUTIVE SUMMARY                                      │
│                                                                          │
│  ✅ وضعیت فعلی: Repository خالی — فرصت طراحی معماری ایده‌آل             │
│                                                                          │
│  📋 معماری پیشنهادی: Modular Monolith → Microservices                    │
│     ├── 28 سرویس مستقل تعریف شده                                       │
│     ├── Plugin architecture برای افزودن ارز/شبکه/سرویس بدون تغییر هسته │
│     ├── Event-Driven communication                                       │
│     └── Double-Entry Ledger با Event Sourcing                           │
│                                                                          │
│  ⏱️ زمان‌بندی:                                                           │
│     ├── Phase 0 (Foundation): 4 هفته                                    │
│     ├── Phase 1 (Core MVP): 10 هفته                                     │
│     ├── Phase 2 (Exchange & Payments): 12 هفته                          │
│     ├── Phase 3 (Scale & Compliance): 14 هفته                           │
│     ├── Phase 4 (Growth & Expansion): 16 هفته                           │
│     └── مجموع: ~56 هفته (14 ماه) تا پلتفرم کامل                        │
│                                                                          │
│  💰 هزینه زیرساخت ماهانه (تخمینی):                                       │
│     ├── Phase 0-1: $500 - $1,500                                        │
│     ├── Phase 2: $1,500 - $3,000                                        │
│     ├── Phase 3: $3,000 - $8,000                                        │
│     └── Phase 4: $8,000 - $20,000                                       │
│                                                                          │
│  🔑 اولویت‌های بحرانی:                                                   │
│     1. Identity & Authentication (بدون آن هیچ چیز کار نمی‌کند)          │
│     2. Ledger (بدون آن پول قابل ردیابی نیست)                            │
│     3. Wallet (بدون آن نگهداری دارایی غیرممکن است)                      │
│     4. Security (بدون آن سرمایه کاربران در خطر است)                     │
│     5. Compliance (بدون آن مجوز مالی دریافت نمی‌شود)                    │
│                                                                          │
│  ⚠️ بزرگ‌ترین ریسک‌ها:                                                   │
│     1. امنیت کیف پول (سرقت دارایی)                                      │
│     2. انطباق با مقررات (AML/KYC)                                       │
│     3. وابستگی به شرکای خارجی (exchange/bank/PSP)                       │
│     4. مقیاس‌پذیری زودهنگام (complexity بدون نیاز)                      │
│     5. Scope creep (تلاش برای ساخت همه چیز همزمان)                     │
│                                                                          │
│  ✨ مزیت کلیدی شروع از صفر:                                             │
│     می‌توان از روز اول بهترین معماری را پیاده‌سازی کرد                   │
│     بدون technical debt، بدون legacy، بدون محدودیت                      │
└─────────────────────────────────────────────────────────────────────────┘
```

---

> **این سند یک نقشه راه معماری سطح Enterprise است. هر بخش نیاز به جلسات تفصیلی با تیم فنی، بررسی فنی عمیق‌تر، و تصمیم‌گیری‌های معماری مشخص (ADR) دارد.**

> **توصیه نهایی: با Phase 0 شروع کنید. زیرساخت را آماده کنید. سپس Phase 1 را با تمرکز بر Identity + Ledger + Wallet اجرا کنید. بقیه مسیر بر اساس تجربه عملیاتی Phase 1 تنظیم خواهد شد.**
