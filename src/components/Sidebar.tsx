import React, { useEffect, useMemo, useState } from 'react';
import * as Icons from 'lucide-react';
import type { MenuItem, menu as MenuArray } from '../menu';
import { menu as defaultMenu } from '../menu';

export type SidebarProps = {
  items?: MenuItem[];
  initialCollapsed?: boolean;
  onCollapsedChange?: (collapsed: boolean) => void;
  currentUserPermissions?: string[]; // list of permission keys user has
};

export default function Sidebar({
  items = defaultMenu,
  initialCollapsed = false,
  onCollapsedChange,
  currentUserPermissions = [],
}: SidebarProps) {
  const [collapsed, setCollapsed] = useState<boolean>(() => {
    try { return localStorage.getItem('sidebar_collapsed') === '1' ? true : initialCollapsed; } catch (e) { return initialCollapsed; }
  });
  const [mobileOpen, setMobileOpen] = useState(false);

  useEffect(() => {
    try { localStorage.setItem('sidebar_collapsed', collapsed ? '1' : '0'); } catch (e) {}
    if (onCollapsedChange) onCollapsedChange(collapsed);
  }, [collapsed, onCollapsedChange]);

  // Filter items by permission server-side or client-provided list
  const visibleItems = useMemo(() => {
    return items.filter(it => {
      if (!it.permission) return true;
      return currentUserPermissions.includes(it.permission);
    });
  }, [items, currentUserPermissions]);

  const currentPath = typeof window !== 'undefined' ? window.location.pathname + window.location.search : '';

  function IconFor(name?: string) {
    if (!name) return Icons.FileText; // fallback
    return (Icons as any)[name] ?? Icons.FileText;
  }

  return (
    <>
      {/* Mobile hamburger */}
      <button className="md:hidden fixed top-4 left-4 z-50 p-2 rounded bg-white shadow text-slate-800" aria-label="Open menu" onClick={() => setMobileOpen(true)}>
        <Icons.Menu size={18} />
      </button>

      <aside className={`fixed top-0 left-0 h-full bg-white text-slate-800 border-r border-slate-200 shadow-sm flex flex-col transition-all duration-300 ${collapsed ? 'w-16' : 'w-64'}`} aria-label="Main navigation">
        <div className="flex items-center gap-3 p-4">
          <div className="text-lg font-bold">{collapsed ? 'AS' : 'AutoShop'}</div>
          <button
            className="ml-auto p-2 rounded hover:bg-gray-100"
            aria-pressed={collapsed}
            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            onClick={() => setCollapsed(c => !c)}
          >
            <span>{collapsed ? '▶' : '◀'}</span>
          </button>
        </div>

        <nav className="flex-1 overflow-y-auto px-2 py-3" role="navigation" aria-label="Primary">
          <ul className="space-y-1">
            {visibleItems.map(it => {
              const Icon = IconFor(it.icon);
              const isActive = currentPath.includes(it.href) || (it.href !== '/' && currentPath === it.href);
              return (
                <li key={it.key}>
                  <a href={it.href} className={`group flex items-center gap-3 px-3 py-2 rounded-lg transition-colors duration-150 ${isActive ? 'bg-slate-100 text-slate-900 font-semibold shadow-sm' : 'text-slate-700 hover:bg-gray-100 hover:text-slate-900'}`} title={it.label}>
                    <span className="w-5 h-5 flex-shrink-0 text-slate-600 group-hover:text-slate-900"><Icon size={16} /></span>
                    <span className={`sidebar-text truncate ${collapsed ? 'hidden' : 'inline'}`}>{it.label}</span>
                  </a>
                </li>
              );
            })}
          </ul>
        </nav>

        <div className={`px-3 py-4 border-t border-slate-100 ${collapsed ? 'items-center' : ''}`}>
          <div className={`flex items-center gap-3 ${collapsed ? 'justify-center' : ''}`}>
            <div className="w-8 h-8 rounded-full bg-slate-200" aria-hidden="true"></div>
            <div className={`${collapsed ? 'hidden' : ''}`}>
              <div className="text-sm font-medium">Admin User</div>
              <a href="/admin/permissions.php" className="text-xs text-slate-500 hover:text-slate-700">Permissions</a>
            </div>
            <a href="/logout.php" className="text-slate-600 hover:text-slate-900 p-1 rounded ml-auto" title="Logout">
              <Icons.LogOut size={18} />
            </a>
          </div>
        </div>
      </aside>

      {/* Mobile drawer */}
      {mobileOpen && (
        <div className="fixed inset-0 z-40 flex">
          <div className="absolute inset-0 bg-black/40" onClick={() => setMobileOpen(false)} aria-hidden="true" />
          <div className="relative w-72 bg-white h-full shadow-lg p-4 animate-slide-in">
            <div className="flex items-center justify-between">
              <div className="text-lg font-bold">AutoShop</div>
              <button className="p-2 rounded hover:bg-gray-100" onClick={() => setMobileOpen(false)} aria-label="Close menu">✕</button>
            </div>

            <nav className="mt-4">
              <ul className="space-y-1">
                {visibleItems.map(it => {
                  const Icon = IconFor(it.icon);
                  const isActive = currentPath.includes(it.href);
                  return (
                    <li key={'m-' + it.key}>
                      <a href={it.href} className={`flex items-center gap-3 px-3 py-2 rounded-lg transition-colors duration-150 ${isActive ? 'bg-slate-100 text-slate-900 font-semibold' : 'text-slate-700 hover:bg-gray-100 hover:text-slate-900'}`} onClick={() => setMobileOpen(false)}>
                        <Icon size={16} />
                        <span>{it.label}</span>
                      </a>
                    </li>
                  );
                })}
              </ul>
            </nav>

            <div className="absolute bottom-4 left-4 right-4">
              <a href="/logout.php" className="flex items-center gap-3 px-3 py-2 rounded hover:bg-gray-100">
                <Icons.LogOut size={18} />
                <span>Logout</span>
              </a>
            </div>
          </div>
        </div>
      )}

      <style>{`
        @keyframes slide-in { from { transform: translateX(-8px); opacity:0 } to { transform: translateX(0); opacity:1 } }
        .animate-slide-in { animation: slide-in .18s ease; }
      `}</style>
    </>
  );
}
