import { useState } from 'react';
import { Form, Input, Button, Card, Typography, Alert, Tabs, message } from 'antd';
import { UserOutlined, LockOutlined, MailOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { api } from '../api/client';

const { Title, Text } = Typography;

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [tab, setTab] = useState<'login' | 'register'>('login');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleLogin = async (values: { login: string; password: string }) => {
    setLoading(true);
    setError(null);
    try {
      await login(values.login, values.password);
      message.success('Welcome back!');
      navigate('/dashboard');
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  const handleRegister = async (values: { first_name: string; last_name: string; email: string; password: string }) => {
    setLoading(true);
    setError(null);
    try {
      await api.post('/auth/register', values);
      message.success('Registration successful! Please sign in.');
      setTab('login');
    } catch (e: any) {
      setError(e.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', padding: 16 }}>
      <Card style={{ width: '100%', maxWidth: 420, borderRadius: 12, boxShadow: '0 20px 60px rgba(0,0,0,0.15)' }}>
        <div style={{ textAlign: 'center', marginBottom: 24 }}>
          <Title level={2} style={{ margin: 0 }}>Next Gen LMS</Title>
          <Text type="secondary">Learning Management System</Text>
        </div>

        <Tabs activeKey={tab} onChange={(k) => { setTab(k as any); setError(null); }} centered items={[
          {
            key: 'login',
            label: 'Sign In',
            children: (
              <Form layout="vertical" onFinish={handleLogin} autoComplete="off">
                <Form.Item name="login" rules={[{ required: true, message: 'Please enter your username' }]}>
                  <Input prefix={<UserOutlined />} placeholder="Username or Email" size="large" />
                </Form.Item>
                <Form.Item name="password" rules={[{ required: true, message: 'Please enter your password' }]}>
                  <Input.Password prefix={<LockOutlined />} placeholder="Password" size="large" />
                </Form.Item>
                {error && <Alert message={error} type="error" showIcon style={{ marginBottom: 16 }} />}
                <Button type="primary" htmlType="submit" loading={loading} block size="large">
                  Sign In
                </Button>
              </Form>
            ),
          },
          {
            key: 'register',
            label: 'Register',
            children: (
              <Form layout="vertical" onFinish={handleRegister} autoComplete="off">
                <Form.Item name="first_name" rules={[{ required: true, message: 'Required' }]}>
                  <Input placeholder="First Name" size="large" />
                </Form.Item>
                <Form.Item name="last_name" rules={[{ required: true, message: 'Required' }]}>
                  <Input placeholder="Last Name" size="large" />
                </Form.Item>
                <Form.Item name="email" rules={[{ required: true, type: 'email', message: 'Valid email required' }]}>
                  <Input prefix={<MailOutlined />} placeholder="Email" size="large" />
                </Form.Item>
                <Form.Item name="password" rules={[{ required: true, min: 8, message: 'Min 8 characters' }]}>
                  <Input.Password prefix={<LockOutlined />} placeholder="Password" size="large" />
                </Form.Item>
                {error && <Alert message={error} type="error" showIcon style={{ marginBottom: 16 }} />}
                <Button type="primary" htmlType="submit" loading={loading} block size="large">
                  Create Account
                </Button>
              </Form>
            ),
          },
        ]} />
      </Card>
    </div>
  );
}
