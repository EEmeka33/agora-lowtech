-- RedefineTables
PRAGMA defer_foreign_keys=ON;
PRAGMA foreign_keys=OFF;
CREATE TABLE "new_User" (
    "id" TEXT NOT NULL PRIMARY KEY,
    "email" TEXT NOT NULL,
    "name" TEXT NOT NULL,
    "passwordHash" TEXT,
    "passwordChangedAt" DATETIME,
    "failedLoginCount" INTEGER NOT NULL DEFAULT 0,
    "lockedUntil" DATETIME,
    "lastLoginAt" DATETIME,
    "phone" TEXT,
    "bio" TEXT,
    "skills" TEXT,
    "avatarColor" TEXT NOT NULL DEFAULT 'indigo',
    "locale" TEXT,
    "emailNotifications" BOOLEAN NOT NULL DEFAULT true,
    "emailDigest" BOOLEAN NOT NULL DEFAULT true,
    "lastDigestAt" DATETIME,
    "calendarTokenHash" TEXT,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);
INSERT INTO "new_User" ("avatarColor", "bio", "calendarTokenHash", "createdAt", "email", "emailNotifications", "failedLoginCount", "id", "lastLoginAt", "locale", "lockedUntil", "name", "passwordChangedAt", "passwordHash", "phone", "skills", "updatedAt") SELECT "avatarColor", "bio", "calendarTokenHash", "createdAt", "email", "emailNotifications", "failedLoginCount", "id", "lastLoginAt", "locale", "lockedUntil", "name", "passwordChangedAt", "passwordHash", "phone", "skills", "updatedAt" FROM "User";
DROP TABLE "User";
ALTER TABLE "new_User" RENAME TO "User";
CREATE UNIQUE INDEX "User_email_key" ON "User"("email");
CREATE UNIQUE INDEX "User_calendarTokenHash_key" ON "User"("calendarTokenHash");
PRAGMA foreign_keys=ON;
PRAGMA defer_foreign_keys=OFF;
