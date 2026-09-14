<?php

declare(strict_types=1);

namespace App\Enums;

enum Permission: string
{
    case AccessAdmin = 'admin.access';
    case ManageStaff = 'staff.manage';
    case ManageSettings = 'settings.manage';
    case ManageCatalog = 'catalog.manage';
    case ViewOrders = 'orders.view';
    case ManageOrders = 'orders.manage';
    case RecordWeights = 'weights.record';
    case ApproveAdjustments = 'payments.approve_adjustments';   // release large underweight captures
    case ManageDeliveries = 'deliveries.manage';
    case ViewReports = 'reports.view';
    case ViewAuditLog = 'audit.view';
}
