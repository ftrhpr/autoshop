export type MenuItem = {
  key: string;
  label: string;
  href: string;
  icon: string; // name of lucide icon
  permission?: string | null;
};

export const menu: MenuItem[] = [
  { key: 'new', label: 'New Invoice', href: '/index.php', icon: 'Plus', permission: null },
  { key: 'dashboard', label: 'Dashboard', href: '/admin/index.php', icon: 'Home', permission: null },
  { key: 'invoices', label: 'Invoices', href: '/manager.php', icon: 'FileText', permission: null },
  { key: 'customers', label: 'Customers', href: '/admin/customers.php', icon: 'Users', permission: 'manage_customers' },
  { key: 'labors', label: 'Labors & Parts', href: '/admin/labors_parts_pro.php', icon: 'Wrench', permission: null },
  { key: 'prices', label: 'Manage Prices', href: '/admin/labors_parts_pro.php', icon: 'Download', permission: 'manage_prices' },
  { key: 'users', label: 'Users', href: '/admin/users.php', icon: 'User', permission: 'manage_users' },
  { key: 'roles', label: 'Roles & Permissions', href: '/admin/permissions.php', icon: 'Shield', permission: 'manage_permissions' },
  { key: 'logs', label: 'Audit Logs', href: '/admin/logs.php', icon: 'Clock', permission: 'view_logs' },
];
