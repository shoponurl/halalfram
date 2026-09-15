<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The six staff roles from the business guideline (M12). Customers have no role.
 */
enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case FrontDesk = 'front_desk';
    case Butcher = 'butcher';
    case Driver = 'driver';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::FrontDesk => 'Front desk',
            self::Butcher => 'Butcher',
            self::Driver => 'Driver',
            self::Accountant => 'Accountant',
        };
    }

    /**
     * Baseline permission matrix. Later sprints add permissions here, never ad hoc in controllers.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => Permission::cases(),
            self::Manager => [
                Permission::AccessAdmin, Permission::ManageCatalog, Permission::ManageInventory, Permission::ViewOrders, Permission::ManageOrders,
                Permission::RecordWeights, Permission::ApproveAdjustments, Permission::ManageDeliveries,
                Permission::ViewReports, Permission::ViewAuditLog, Permission::ManageCompliance,
            ],
            self::FrontDesk => [Permission::AccessAdmin, Permission::ViewOrders, Permission::ManageOrders],
            self::Butcher => [Permission::AccessAdmin, Permission::ViewOrders, Permission::RecordWeights, Permission::ManageInventory],
            self::Driver => [Permission::AccessAdmin, Permission::ManageDeliveries],
            self::Accountant => [Permission::AccessAdmin, Permission::ViewOrders, Permission::ViewReports, Permission::ViewAuditLog],
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
