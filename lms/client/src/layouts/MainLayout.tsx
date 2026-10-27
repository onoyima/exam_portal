import { useState, useMemo } from 'react';
import { Layout, Menu, Button, Avatar, Dropdown, Badge, Tag, Typography, theme } from 'antd';
import {
  DashboardOutlined,
  BookOutlined,
  MessageOutlined,
  BankOutlined,
  LogoutOutlined,
  MenuFoldOutlined,
  MenuUnfoldOutlined,
  UserOutlined,
  FormOutlined,
  TeamOutlined,
  BarChartOutlined,
  ProfileOutlined,
  ReadOutlined,
  BellOutlined,
  SettingOutlined,
} from '@ant-design/icons';
import { Outlet, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

const { Header, Sider, Content } = Layout;
const { Text } = Typography;

export default function MainLayout() {
  const [collapsed, setCollapsed] = useState(false);
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout } = useAuth();
  const { token: themeToken } = theme.useToken();

  const role = user?.role || user?.portal_user_type || '';
  const isStudent = role === 'student';
  const isStaff = role === 'examiner' || role === 'instructor' || role === 'ta';
  const isAdmin = role === 'super_admin' || role === 'admin';

  const roleColor = isStudent ? 'green' : isStaff ? 'blue' : isAdmin ? 'purple' : 'default';
  const roleLabel = isStudent ? 'Student' : isStaff ? 'Staff' : isAdmin ? 'Admin' : role;

  const menuItems = useMemo(() => [
    { key: '/dashboard', icon: <DashboardOutlined />, label: 'Dashboard' },
    { key: '/courses', icon: <BookOutlined />, label: 'Courses' },
    ...(isStaff || isAdmin ? [{ key: '/my-courses', icon: <ReadOutlined />, label: 'My Courses' }] : []),
    { key: '/classes', icon: <TeamOutlined />, label: 'Classes' },
    { key: '/gradebook', icon: <ProfileOutlined />, label: isStaff ? 'Gradebook' : 'Grades' },
    ...(isStaff || isAdmin ? [{ key: '/analytics', icon: <BarChartOutlined />, label: 'Analytics' }] : []),
    ...(isStudent ? [{ key: '/registrations', icon: <FormOutlined />, label: 'Registrations' }] : []),
    { key: '/academics', icon: <BankOutlined />, label: 'Academics' },
    { key: '/messages', icon: <MessageOutlined />, label: 'Messages' },
    ...(isStaff || isAdmin ? [{ key: '/settings', icon: <SettingOutlined />, label: 'Settings' }] : []),
  ], [isStudent, isStaff, isAdmin]);

  const selectedKey = '/' + location.pathname.split('/').filter(Boolean)[0] || '/dashboard';

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const userMenu = {
    items: [
      { key: 'profile', icon: <UserOutlined />, label: 'Profile' },
      { type: 'divider' as const },
      { key: 'logout', icon: <LogoutOutlined />, label: 'Logout', danger: true },
    ],
    onClick: ({ key }: { key: string }) => {
      if (key === 'profile') navigate('/profile');
      if (key === 'logout') handleLogout();
    },
  };

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Sider
        trigger={null}
        collapsible
        collapsed={collapsed}
        breakpoint="lg"
        style={{
          overflow: 'auto',
          height: '100vh',
          position: 'fixed',
          left: 0,
          top: 0,
          bottom: 0,
          background: themeToken.colorBgContainer,
          borderRight: `1px solid ${themeToken.colorBorderSecondary}`,
        }}
      >
        <div style={{ height: 64, display: 'flex', alignItems: 'center', justifyContent: 'center', borderBottom: `1px solid ${themeToken.colorBorderSecondary}` }}>
          <Text strong style={{ fontSize: collapsed ? 16 : 20, color: themeToken.colorPrimary }}>
            {collapsed ? 'VL' : 'Next Gen LMS'}
          </Text>
        </div>
        <Menu
          mode="inline"
          selectedKeys={[selectedKey]}
          items={menuItems}
          onClick={({ key }) => navigate(key)}
          style={{ border: 'none' }}
        />
      </Sider>
      <Layout style={{ marginLeft: collapsed ? 80 : 200, transition: 'margin-left 0.2s' }}>
        <Header
          style={{
            padding: '0 24px',
            background: themeToken.colorBgContainer,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            borderBottom: `1px solid ${themeToken.colorBorderSecondary}`,
            height: 64,
            position: 'sticky',
            top: 0,
            zIndex: 10,
          }}
        >
          <Button
            type="text"
            icon={collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
            onClick={() => setCollapsed(!collapsed)}
          />
          <div style={{ display: 'flex', alignItems: 'center', gap: 16 }}>
            <Badge count={3} size="small">
              <BellOutlined style={{ fontSize: 18, cursor: 'pointer' }} />
            </Badge>
            <Tag color={roleColor} style={{ marginRight: 0 }}>{roleLabel}</Tag>
            <Dropdown menu={userMenu} placement="bottomRight">
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}>
                <Avatar icon={<UserOutlined />} style={{ backgroundColor: themeToken.colorPrimary }} />
                <Text>{user?.first_name || 'User'}</Text>
              </div>
            </Dropdown>
          </div>
        </Header>
        <Content style={{ margin: 24, minHeight: 280 }}>
          <Outlet />
        </Content>
      </Layout>
    </Layout>
  );
}
