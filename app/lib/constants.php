<?php
/*
 * Allowed values of every enumerated column. Labels live in app/lang/*.php
 * under "enums.<name>.<VALUE>".
 */

const ROLES = ['ADMIN', 'MANAGER', 'MEMBER'];
const BOARD_POSITIONS = ['PRESIDENT', 'VICE_PRESIDENT', 'SECRETARY', 'DEPUTY_SECRETARY', 'TREASURER', 'DEPUTY_TREASURER', 'BOARD_MEMBER'];
const MEMBER_STATUSES = ['ACTIVE', 'INACTIVE'];
const POLE_ROLES = ['LEAD', 'MEMBER'];
const PROJECT_STATUSES = ['IDEA', 'PLANNED', 'IN_PROGRESS', 'ON_HOLD', 'COMPLETED', 'CANCELLED'];
const PRIORITIES = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'];
const TASK_STATUSES = ['TODO', 'IN_PROGRESS', 'REVIEW', 'DONE'];
const HEALTH = ['ON_TRACK', 'AT_RISK', 'OFF_TRACK'];
const STEP_STATUSES = ['TODO', 'IN_PROGRESS', 'DONE', 'SKIPPED'];
const STEP_CATEGORIES = ['PROJECT', 'LEGAL', 'GOVERNANCE', 'FINANCE', 'OPERATIONS', 'COMMUNICATION'];
const FREQUENCIES = ['ONGOING', 'WEEKLY', 'MONTHLY', 'QUARTERLY', 'YEARLY', 'ONE_OFF'];
const EVENT_TYPES = ['GENERAL_ASSEMBLY', 'BOARD_MEETING', 'POLE_MEETING', 'EVENT', 'WORKSHOP', 'OTHER'];
const RSVP = ['GOING', 'MAYBE', 'NOT_GOING'];
const TRANSACTION_TYPES = ['INCOME', 'EXPENSE'];
const INCOME_CATEGORIES = ['MEMBERSHIP', 'DONATION', 'GRANT', 'EVENT', 'SALES', 'SPONSORSHIP', 'OTHER'];
const EXPENSE_CATEGORIES = ['SUPPLIES', 'EVENT', 'RENT', 'INSURANCE', 'BANK', 'TRAVEL', 'COMMUNICATION', 'SOFTWARE', 'FOOD', 'OTHER'];
const PAYMENT_METHODS = ['CARD', 'TRANSFER', 'CASH', 'CHECK', 'ONLINE', 'OTHER'];
const DOCUMENT_CATEGORIES = ['STATUTES', 'RULES', 'MINUTES', 'FINANCE', 'LEGAL', 'COMMUNICATION', 'GUIDE', 'OTHER'];
const REQUEST_STATUSES = ['OPEN', 'TAKEN', 'DONE', 'CANCELLED'];
const EMAIL_MODES = ['DAILY', 'INSTANT', 'OFF'];
const LOCALES = ['en', 'fr'];
const CURRENCIES = ['EUR', 'USD', 'GBP', 'CHF', 'CAD'];

/** Both lists of transaction categories, without duplicates. */
function transaction_categories(): array
{
    return array_values(array_unique(array_merge(INCOME_CATEGORIES, EXPENSE_CATEGORIES)));
}

/** Instance-wide sign-up policy: open | invite | closed. */
function signup_mode(): string
{
    $mode = config('signup_mode');
    return in_array($mode, ['invite', 'closed'], true) ? $mode : 'open';
}

function new_associations_allowed(): bool
{
    return config('allow_new_associations') !== false;
}
