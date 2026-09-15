<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Guideline ch. 8 (launch gate) plus the Sprint 09 Definition of Done. Three kinds of proof:
 * automated checks run live against this environment, drills recorded by their own command, and
 * manual items a named person signs off with `launch:attest` (e.g. a third-party pentest report).
 */
enum LaunchGateItem: string
{
    // Automated — evaluated live, every time.
    case DebugOff = 'debug_off';
    case HttpsOnly = 'https_only';
    case StaffTwoFactor = 'staff_2fa';
    case NoDevAccounts = 'no_dev_accounts';
    case DatabaseLeastPrivilege = 'db_least_privilege';
    case LivePayments = 'live_payments';
    case QueueAsync = 'queue_async';
    case MailConfigured = 'mail_configured';
    case AlertsConfigured = 'alerts_configured';
    case Reconciliation = 'reconciliation';

    // Drills — recorded by their command; must be a recent pass in this environment.
    case RestoreDrill = 'restore_drill';
    case RecallDrill = 'recall_drill';
    case OversellDrill = 'oversell_drill';
    case LoadTest = 'load_test';

    // Manual — a person attests, with a pointer to the evidence.
    case PentestClosed = 'pentest_closed';
    case SelfAuditClosed = 'self_audit_closed';
    case PciSaqA = 'pci_saq_a';
    case UsdaConsent = 'usda_consent';
    case Sms10Dlc = 'sms_10dlc';
    case EmailInbox = 'email_inbox';
    case KeysRotated = 'keys_rotated';
    case EncryptedBackups = 'encrypted_backups';
    case WcagAudit = 'wcag_audit';
    case PrivacyLive = 'privacy_live';
    case RunbookWritten = 'runbook_written';
    case AlertReachedPerson = 'alert_reached_person';
    case PaymentMethodsTested = 'payment_methods_tested';
    case ColdChainShipment = 'cold_chain_shipment';
    case StaffDryRun = 'staff_dry_run';

    public function kind(): string
    {
        return match ($this) {
            self::DebugOff, self::HttpsOnly, self::StaffTwoFactor, self::NoDevAccounts, self::DatabaseLeastPrivilege,
            self::LivePayments, self::QueueAsync, self::MailConfigured, self::AlertsConfigured, self::Reconciliation => 'automated',
            self::RestoreDrill, self::RecallDrill, self::OversellDrill, self::LoadTest => 'drill',
            default => 'manual',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::DebugOff => 'Production mode, APP_DEBUG off',
            self::HttpsOnly => 'HTTPS app URL and secure session cookie',
            self::StaffTwoFactor => 'Every active staff account has 2FA',
            self::NoDevAccounts => 'No development/demo accounts',
            self::DatabaseLeastPrivilege => 'Production DB user cannot DROP or ALTER',
            self::LivePayments => 'Live Stripe keys and webhook secret (PCI: card data only ever at Stripe)',
            self::QueueAsync => 'Queue is a real worker, not sync',
            self::MailConfigured => 'Transactional mail goes through Postmark',
            self::AlertsConfigured => 'Alerts channel is on and has a destination',
            self::Reconciliation => 'Ledger reconciliation matches',
            self::RestoreDrill => 'Full restore from an encrypted backup, with recovery time',
            self::RecallDrill => 'Recall drill finds every affected order within 30 seconds',
            self::OversellDrill => 'No oversell under concurrent checkouts',
            self::LoadTest => 'Load test at 10x normal traffic passes',
            self::PentestClosed => 'Third-party penetration test: all Critical and High closed',
            self::SelfAuditClosed => 'Self-audit: all Critical and High closed, each with a regression test',
            self::PciSaqA => 'PCI-DSS SAQ-A completed',
            self::UsdaConsent => 'USDA/PA position confirmed; checkout consent matches it and is recorded',
            self::Sms10Dlc => 'A2P 10DLC approved; a real SMS arrives and STOP works immediately',
            self::EmailInbox => 'Email is authenticated (SPF/DKIM/DMARC) and lands in the inbox',
            self::KeysRotated => 'Every production key rotated after development',
            self::EncryptedBackups => 'Automated backups are encrypted and stored off the server',
            self::WcagAudit => 'WCAG 2.1 AA verified across the full purchase flow',
            self::PrivacyLive => 'Privacy policy, terms, cookie consent and CCPA queue live (legal-reviewed)',
            self::RunbookWritten => 'Runbook and staff guide written and read by the team',
            self::AlertReachedPerson => 'A test alert reached a real person',
            self::PaymentMethodsTested => 'One successful test order per payment method',
            self::ColdChainShipment => 'A real package arrived cold, with a temperature logger',
            self::StaffDryRun => 'Front desk and butcher ran the system themselves for a day',
        };
    }
}
