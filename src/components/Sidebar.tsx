import React, { useEffect, useState } from 'react';
import {
  Home,
  FileText,
  Users,
  Wrench,
  Download,
  User,
  Shield,
  Clock,
  Plus,
  Menu,
  X,
  Settings,
} from 'lucide-react';

export interface NavItem {
  key: string;
  label: string;
  href: string;
  icon: React.ComponentType<React.SVGProps<SVGSVGElement>>;
  permission?: string;
}

export interface SidebarUser {
  name: string;
  email?: string;
}

interface SidebarProps {
  items?: NavItem[];
  user?: SidebarUser;
}

const DEFAULT_ITEMS: NavItem[] = [
  { key: 'new', label: 'New Invoice', href: '/index.php', icon: Plus },
  { key: 'dashboard', label: 'Dashboard', href: '/admin/index.php', icon: Home },
  { key: 'invoices', label: 'Invoices', href: '/manager.php', icon: FileText },
  { key: 'customers', label: 'Customers', href: '/admin/customers.php', icon: Users },
  { key: 'labors', label: 'Labors & Parts', href: '/admin/labors_parts_pro.php', icon: Wrench },
  { key: 'prices', label: 'Manage Prices', href: '/admin/labors_parts_pro.php', icon: Download },
  { key: 'users', label: 'Users', href: '/admin/users.php', icon: User },
  { key: 'roles', label: 'Roles & Permissions', href: '/admin/permissions.php', icon: Shield },
  { key: 'logs', label: 'Audit Logs', href: '/admin/logs.php', icon: Clock },
];

const Sidebar: React.FC<SidebarProps> = ({ items = DEFAULT_ITEMS, user = { name: 'Admin' } }) => {
  const [collapsed, setCollapsed] = useState<boolean>(false);
  const [mobileOpen, setMobileOpen] = useState<boolean>(false);

  useEffect(() => {
    try {
      const s = localStorage.getItem('sidebar_collapsed');
      if (s === '1') setCollapsed(true);
    } catch (e) {
      // ignore
    }
  }, []);

  useEffect(() => {
    try {
      localStorage.setItem('sidebar_collapsed', collapsed ? '1' : '0');
    } catch (e) {
      // ignore
    }
  }, [collapsed]);

  const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
  const isActive = (href: string): boolean => {
    try {
      const base = typeof window !== 'undefined' ? window.location.origin : '';
      const h = new URL(href, base).pathname;
      return currentPath === h || currentPath.startsWith(h.replace(/\/$/, ''));
    } catch (e) {
      return currentPath === href;
    }
  };

  return (
    <>
      {/* Desktop */}
      <aside
        className={`hidden md:flex flex-col h-screen fixed left-0 top-0 bg-white border-r border-gray-200 shadow-sm transition-all duration-200 ease-in-out z-40 ${
          collapsed ? 'w-16' : 'w-64'
        }`}
        role="navigation"
        aria-label="Primary navigation"
      >
        <div className="flex items-center gap-3 px-3 py-4 border-b border-gray-100">
          <div className="flex items-center gap-2 w-full">
            <div className="bg-blue-600 text-white rounded p-1 flex items-center justify-center">
              <Home className="h-5 w-5" />
            </div>
            {!collapsed && <span className="font-bold text-lg">AutoShop</span>}
            <button
              aria-label="Toggle collapse"
              aria-pressed={collapsed}
              onClick={() => setCollapsed((s) => !s)}
              className="ml-auto text-gray-500 hover:text-gray-700 p-1 rounded focus:outline-none"
              title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                className="h-4 w-4"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
              >
                <path d={collapsed ? 'M9 6l6 6-6 6' : 'M15 6l-6 6 6 6'} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
              </svg>
            </button>
          </div>
        </div>

        <nav className="flex-1 overflow-y-auto p-2 space-y-1">
          {items.map((it) => {
            const Icon = it.icon;
            const active = isActive(it.href);
            return (
              <a
                key={it.key}
                href={it.href}
                className={`flex items-center gap-3 px-3 py-2 rounded-lg transition-colors duration-150 hover:bg-gray-100 hover:text-gray-900 ${
                  active ? 'bg-yellow-500 text-slate-900 font-semibold shadow-md' : 'text-gray-700'
                }`}
                aria-current={active ? 'page' : undefined}
              >
                <span className="flex items-center justify-center w-6 h-6">
                  <Icon className="w-5 h-5" />
                </span>
                {!collapsed && <span className="sidebar-label">{it.label}</span>}
              </a>
            );
          })}
        </nav>

        <div className="p-3 border-t border-gray-100">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-gray-700">A</div>
            {!collapsed && (
              <div className="flex-1 text-sm">
                <div className="font-medium">{user.name}</div>
                {user.email && <div className="text-xs text-gray-500 truncate">{user.email}</div>}
              </div>
            )}
            {!collapsed && (
              <button className="text-gray-500 hover:text-gray-700 p-1 rounded focus:outline-none" title="Settings">
                <Settings className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>
      </aside>

      {/* Mobile */}
      <div className="md:hidden">
        <button
          onClick={() => setMobileOpen(true)}
          className="fixed bottom-4 left-4 z-50 bg-blue-600 text-white p-3 rounded-full shadow-lg"
          aria-label="Open menu"
        >
          <Menu className="w-5 h-5" />
        </button>

        {mobileOpen && (
          <div className="fixed inset-0 z-50">
            <div className="absolute inset-0 bg-black/40" onClick={() => setMobileOpen(false)} aria-hidden="true" />
            <div className="absolute inset-y-0 left-0 w-64 bg-white border-r border-gray-200 shadow-lg transform transition-transform duration-200">
              <div className="p-3 border-b border-gray-100 flex items-center gap-3">
                <div className="bg-blue-600 text-white rounded p-1 flex items-center justify-center">
                  <Home className="h-5 w-5" />
                </div>
                <div className="font-bold">AutoShop</div>
                <button onClick={() => setMobileOpen(false)} className="ml-auto text-gray-500 p-1">
                  <X className="w-5 h-5" />
                </button>
              </div>

              <nav className="p-3 space-y-1">
                {items.map((it) => {
                  const Icon = it.icon;
                  const active = isActive(it.href);
                  return (
                    <a
                      key={it.key}
                      href={it.href}
                      onClick={() => setMobileOpen(false)}
                      className={`flex items-center gap-3 px-3 py-2 rounded-lg transition-colors duration-150 hover:bg-gray-100 hover:text-gray-900 ${
                        active ? 'bg-yellow-500 text-slate-900 font-semibold' : 'text-gray-700'
                      }`}
                    >
                      <span className="flex items-center justify-center w-6 h-6">
                        <Icon className="w-5 h-5" />
                      </span>
                      <span>{it.label}</span>
                    </a>
                  );
                })}
              </nav>

              <div className="p-3 border-t border-gray-100">
                <div className="flex items-center gap-3">
                  <div className="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-gray-700">A</div>
                  <div className="flex-1 text-sm">
                    <div className="font-medium">{user.name}</div>
                    {user.email && <div className="text-xs text-gray-500 truncate">{user.email}</div>}
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </>
  );
};

export default Sidebar;
