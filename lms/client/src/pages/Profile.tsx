import { useState, useEffect } from 'react';
import { Card, Typography, Form, Input, Button, Spin, Avatar, Row, Col, Divider, message, Descriptions, Tag } from 'antd';
import { UserOutlined, SaveOutlined } from '@ant-design/icons';
import { useAuth } from '../contexts/AuthContext';
import { api } from '../api/client';

const { Title } = Typography;

export default function Profile() {
  const { user } = useAuth();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (user) {
      form.setFieldsValue({
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        email: user.email || '',
        phone: (user as any).phone || '',
      });
      setLoading(false);
    }
  }, [user, form]);

  const handleSave = async (values: any) => {
    setSaving(true);
    try {
      await api.put('/auth/profile', values);
      message.success('Profile updated successfully');
    } catch {
      message.error('Failed to update profile');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return <div style={{ textAlign: 'center', padding: 80 }}><Spin size="large" /></div>;

  return (
    <div>
      <Title level={4} style={{ marginBottom: 24 }}>My Profile</Title>
      <Row gutter={[24, 24]}>
        <Col xs={24} md={8}>
          <Card style={{ textAlign: 'center' }}>
            <Avatar size={120} icon={<UserOutlined />} style={{ marginBottom: 16 }} />
            <Title level={4}>{user?.first_name} {user?.last_name}</Title>
            <Tag color="blue">{user?.portal_user_type || 'User'}</Tag>
            <Divider />
            <Descriptions column={1} size="small">
              <Descriptions.Item label="Email">{user?.email}</Descriptions.Item>
              <Descriptions.Item label="Status">
                <Tag color="green">Active</Tag>
              </Descriptions.Item>
              <Descriptions.Item label="Member Since">
                {user?.created_at ? new Date(user.created_at).toLocaleDateString() : '-'}
              </Descriptions.Item>
            </Descriptions>
          </Card>
        </Col>
        <Col xs={24} md={16}>
          <Card title="Edit Profile">
            <Form
              form={form}
              layout="vertical"
              onFinish={handleSave}
              style={{ maxWidth: 500 }}
            >
              <Row gutter={16}>
                <Col span={12}>
                  <Form.Item name="first_name" label="First Name" rules={[{ required: true, message: 'Required' }]}>
                    <Input />
                  </Form.Item>
                </Col>
                <Col span={12}>
                  <Form.Item name="last_name" label="Last Name" rules={[{ required: true, message: 'Required' }]}>
                    <Input />
                  </Form.Item>
                </Col>
              </Row>
              <Form.Item name="email" label="Email" rules={[{ required: true, type: 'email', message: 'Valid email required' }]}>
                <Input disabled />
              </Form.Item>
              <Form.Item name="phone" label="Phone">
                <Input />
              </Form.Item>
              <Form.Item>
                <Button type="primary" htmlType="submit" loading={saving} icon={<SaveOutlined />}>
                  Save Changes
                </Button>
              </Form.Item>
            </Form>
          </Card>
        </Col>
      </Row>
    </div>
  );
}
