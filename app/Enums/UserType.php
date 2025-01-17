<?php

namespace App\Enums;

use App\Traits\SmartEnum;

enum UserType: int
{
    use SmartEnum;

    case SUPER_ADMINISTRATOR = 1; // Octiv Super Admin
    case HEAD_COACH = 2; // guy that runs stuff... or signed up...
    case GYM_COACH = 3; // staff
    case GYM_MEMBER = 4; // members
    case BOX_ADMIN = 5; // assigned admin
    case BOX_FACILITY_ADMIN = 6; // assigned admin for facility
    case ADMIN = 7; // Octiv Admin
    case LOCATION_CHECK_IN = 8; // Self-assist client?
    case LEAD_MEMBER = 9; // Lead member
    case DISCOVERY = 10; // Discovery admin
    case USER = 11; // A user that can have a tenant.

    public function toString(): string
    {
        return match ($this) {
            self::SUPER_ADMINISTRATOR => 'Super Administrator',
            self::HEAD_COACH => 'Owner',
            self::GYM_COACH => 'Gym Coach',
            self::GYM_MEMBER => 'Gym Member',
            self::BOX_ADMIN => 'Box Admin',
            self::BOX_FACILITY_ADMIN => 'Box Facility Admin',
            self::ADMIN => 'Admin',
            self::LOCATION_CHECK_IN => 'Location Check-in',
            self::LEAD_MEMBER => 'Lead Member',
            self::DISCOVERY => 'Discovery',
            self::USER => 'User',
        };
    }

    public static function tenantUserIds(): array
    {
        return [
            self::HEAD_COACH->value,
            self::BOX_ADMIN->value,
            self::BOX_FACILITY_ADMIN->value,
            self::GYM_COACH->value,
            self::LOCATION_CHECK_IN->value,
            self::GYM_MEMBER->value,
            self::LEAD_MEMBER->value,
        ];
    }

    public static function admins(): array
    {
        return [
            self::SUPER_ADMINISTRATOR->value,
            self::ADMIN->value,
        ];
    }

    public static function coachTypeIds(): array
    {
        return [
            self::HEAD_COACH->value,
            self::GYM_COACH->value,
        ];
    }

    public static function staffUserTypeIds(): array
    {
        return [
            self::HEAD_COACH->value,
            self::BOX_ADMIN->value,
            self::BOX_FACILITY_ADMIN->value,
            self::GYM_COACH->value,
            self::LOCATION_CHECK_IN->value,
        ];
    }

    /**
     * HEAD_COACH and BOX_ADMIN
     */
    public static function tenantAdmins(): array
    {
        return [
            self::HEAD_COACH,
            self::BOX_ADMIN,
        ];
    }

    /**
     * HEAD_COACH, BOX_ADMIN and BOX_FACILITY_ADMIN
     */
    public static function locationAdmins(): array
    {
        return [
            self::HEAD_COACH,
            self::BOX_ADMIN,
            self::BOX_FACILITY_ADMIN,
        ];
    }

    /**
     * GYM_MEMBER and LEAD_MEMBER
     */
    public static function tenantMembers(): array
    {
        return [
            self::GYM_MEMBER,
            self::LEAD_MEMBER,
        ];
    }

    /**
     * HEAD_COACH, BOX_ADMIN, BOX_FACILITY_ADMIN and GYM_COACH
     */
    public static function tenantStaff(): array
    {
        return [
            self::HEAD_COACH,
            self::BOX_ADMIN,
            self::BOX_FACILITY_ADMIN,
            self::GYM_COACH,
        ];
    }

    /**
     * HEAD_COACH, BOX_ADMIN, BOX_FACILITY_ADMIN, GYM_COACH, GYM_MEMBER and LEAD_MEMBER
     */
    public static function tenantUsers(): array
    {
        return [
            self::HEAD_COACH,
            self::BOX_ADMIN,
            self::BOX_FACILITY_ADMIN,
            self::GYM_COACH,
            self::GYM_MEMBER,
            self::LEAD_MEMBER,
        ];
    }

    public static function allRoles(): array
    {
        return [
            self::SUPER_ADMINISTRATOR,
            self::HEAD_COACH,
            self::BOX_ADMIN,
            self::BOX_FACILITY_ADMIN,
            self::GYM_COACH,
            self::GYM_MEMBER,
            self::LEAD_MEMBER,
        ];
    }

    public function getRole(): string
    {
        return match ($this->name) {
            self::SUPER_ADMINISTRATOR => 'ROLE_SUPER_ADMIN',
            self::ADMIN => 'ROLE_ADMIN',
            default => 'ROLE_USER',
        };
    }

    /**
     * Maps user types to Spatie roles
     */
    public function toRoleName(): ?string
    {
        return match ($this) {
            self::SUPER_ADMINISTRATOR => null,

            self::HEAD_COACH => 'Head Coach',
            self::GYM_COACH => 'Gym Coach',
            self::GYM_MEMBER => 'Gym Member',
            self::BOX_ADMIN => 'Box Admin',
            self::BOX_FACILITY_ADMIN => 'Box Facility Admin',
            self::LEAD_MEMBER => 'Lead Member',
            self::ADMIN => null,
            self::LOCATION_CHECK_IN => null,
        };
    }
}
