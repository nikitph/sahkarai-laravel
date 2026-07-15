<?php

namespace App\Models;

enum Permission: string
{
    case ManageOrganization = 'organization.manage';
    case ManageMembers = 'members.manage';
    case ViewAuditLog = 'audit.view';
    case ManageProjects = 'projects.manage';
}
