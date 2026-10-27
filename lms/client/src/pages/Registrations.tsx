import { useState, useEffect } from 'react';
import { Card, Table, Collapse, Tag, Typography, Spin, Empty, Alert, Space, Badge, Button } from 'antd';
import { BookOutlined, CalendarOutlined, CheckCircleOutlined, CloseCircleOutlined, ReadOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../contexts/AuthContext';

const { Title, Text } = Typography;

interface CourseReg {
  reg_id: number;
  course_id: number;
  course_code: string;
  course_title: string;
  credit_load: number;
  level_name: string;
  ca_one: string | null;
  ca_two: string | null;
  ca_three: string | null;
  examination: string | null;
  total: string | null;
  grade: string | null;
  is_course_reg: number;
}

interface RegGroup {
  vu_session_id: number;
  vu_session_name: string;
  semester_id: number;
  semester_name: string;
  academic_session_id: number;
  session_start_date: string;
  session_end_date: string;
  courses: CourseReg[];
}

function gradeColor(grade: string | null): string {
  if (!grade) return 'default';
  const g = grade.trim().toUpperCase();
  if (g === 'A' || g === 'B') return 'green';
  if (g === 'C' || g === 'D') return 'blue';
  return 'orange';
}

export default function Registrations() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const role = user?.role || user?.portal_user_type || '';
  const isStaff = role === 'examiner' || role === 'instructor' || role === 'ta';

  const [academic, setAcademic] = useState<any>(null);
  const [activeSession, setActiveSession] = useState<any>(null);
  const [registrations, setRegistrations] = useState<RegGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api.get('/academic/my-registrations').then((res: any) => {
      setAcademic(res.academic);
      setActiveSession(res.active_session);
      setRegistrations(res.registrations || []);
    }).catch((err: any) => {
      setError(err.message || 'Failed to load registrations');
    }).finally(() => setLoading(false));
  }, []);

  if (loading) return <div style={{ textAlign: 'center', padding: 80 }}><Spin size="large" /></div>;

  if (isStaff) {
    return (
      <div>
        <Title level={4} style={{ marginBottom: 16 }}>Registrations</Title>
        <Card>
          <Alert
            message="Student-only page"
            description="Course registrations and results are only available for students. View your teaching courses instead."
            type="info"
            showIcon
            action={<Button type="primary" onClick={() => navigate('/my-courses')} icon={<ReadOutlined />}>My Courses</Button>}
          />
        </Card>
      </div>
    );
  }

  if (error) {
    return (
      <div>
        <Title level={4} style={{ marginBottom: 16 }}>My Registrations</Title>
        <Card>
          <Alert
            message="Could not load registrations"
            description={error}
            type="warning"
            showIcon
          />
        </Card>
      </div>
    );
  }

  if (registrations.length === 0) {
    return (
      <div>
        <Title level={4} style={{ marginBottom: 16 }}>My Registrations</Title>
        {activeSession && (
          <Alert
            message={`Active Session: ${activeSession.session}`}
            type="info"
            showIcon
            icon={<CalendarOutlined />}
            style={{ marginBottom: 16 }}
          />
        )}
        <Card><Empty description="No course registrations found." /></Card>
      </div>
    );
  }

  const columns = [
    {
      title: 'Course Code',
      dataIndex: 'course_code',
      key: 'code',
      width: 120,
      render: (v: string) => <Text code>{v}</Text>,
    },
    {
      title: 'Course Title',
      dataIndex: 'course_title',
      key: 'title',
    },
    {
      title: 'Credit',
      dataIndex: 'credit_load',
      key: 'credit',
      width: 70,
      align: 'center' as const,
    },
    {
      title: 'CA 1',
      dataIndex: 'ca_one',
      key: 'ca1',
      width: 70,
      align: 'center' as const,
      render: (v: string | null) => v ?? '-',
    },
    {
      title: 'CA 2',
      dataIndex: 'ca_two',
      key: 'ca2',
      width: 70,
      align: 'center' as const,
      render: (v: string | null) => v ?? '-',
    },
    {
      title: 'CA 3',
      dataIndex: 'ca_three',
      key: 'ca3',
      width: 70,
      align: 'center' as const,
      render: (v: string | null) => v ?? '-',
    },
    {
      title: 'Exam',
      dataIndex: 'examination',
      key: 'exam',
      width: 70,
      align: 'center' as const,
      render: (v: string | null) => v ?? '-',
    },
    {
      title: 'Total',
      dataIndex: 'total',
      key: 'total',
      width: 70,
      align: 'center' as const,
      render: (v: string | null) => v ? <Text strong>{v}</Text> : '-',
    },
    {
      title: 'Grade',
      dataIndex: 'grade',
      key: 'grade',
      width: 80,
      align: 'center' as const,
      render: (v: string | null) => v
        ? <Tag color={gradeColor(v)}>{v.trim().toUpperCase()}</Tag>
        : '-',
    },
  ];

  const collapseItems = registrations.map((group, idx) => ({
    key: String(idx),
    label: (
      <Space>
        <Badge status={activeSession?.id === group.vu_session_id ? 'processing' : 'default'} />
        <Text strong>{group.vu_session_name}</Text>
        <Tag>{group.semester_name}</Tag>
        <Text type="secondary" style={{ fontSize: 12 }}>
          {group.courses.length} course{group.courses.length !== 1 ? 's' : ''}
        </Text>
        {activeSession?.id === group.vu_session_id && (
          <Tag color="green">Active</Tag>
        )}
      </Space>
    ),
    children: (
      <Table
        dataSource={group.courses}
        columns={columns}
        rowKey="reg_id"
        pagination={false}
        size="small"
        bordered
      />
    ),
  }));

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>My Registrations & Results</Title>

      {activeSession && (
        <Alert
          message={`Active Session: ${activeSession.session}`}
          type="info"
          showIcon
          icon={<CalendarOutlined />}
          style={{ marginBottom: 16 }}
        />
      )}

      {academic && (
        <Card size="small" style={{ marginBottom: 16 }}>
          <Space wrap>
            <Text><BookOutlined /> {academic.course_study_name || 'N/A'}</Text>
            <Text>|</Text>
            <Text>{academic.department_name || 'N/A'}</Text>
            <Text>|</Text>
            <Text>{academic.faculty_name || 'N/A'}</Text>
            <Text>|</Text>
            <Text>Level: {academic.current_level || 'N/A'}</Text>
          </Space>
        </Card>
      )}

      <Collapse
        items={collapseItems}
        defaultActiveKey={registrations.length > 0 ? ['0'] : undefined}
        style={{ background: 'transparent' }}
      />
    </div>
  );
}
