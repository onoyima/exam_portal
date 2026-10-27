import { useState, useEffect } from 'react';
import { Row, Col, Card, Typography, Spin, Empty, Table, Statistic, Tag, Select, Space, Progress } from 'antd';
import { RiseOutlined, UserOutlined, CheckCircleOutlined, ClockCircleOutlined } from '@ant-design/icons';
import { api } from '../api/client';

const { Title } = Typography;

export default function Analytics() {
  const [courses, setCourses] = useState<any[]>([]);
  const [selectedCourse, setSelectedCourse] = useState<string | undefined>();
  const [stats, setStats] = useState<any>(null);
  const [engagement, setEngagement] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/courses').then((res: any) => {
      setCourses(res?.courses || res || []);
    }).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    const endpoint = selectedCourse
      ? `/analytics/course/${selectedCourse}`
      : '/analytics/platform';
    Promise.all([
      api.get(endpoint).catch(() => null),
      selectedCourse ? api.get(`/analytics/course/${selectedCourse}/engagement`).catch(() => ([] as any[])) : Promise.resolve([] as any[]),
    ]).then(([s, e]) => {
      setStats(s);
      setEngagement(e || []);
    }).finally(() => setLoading(false));
  }, [selectedCourse]);

  const engColumns = [
    { title: 'Student', dataIndex: 'student_name', key: 'student' },
    {
      title: 'Progress', dataIndex: 'progress_percent', key: 'progress',
      render: (p: number) => <Progress percent={Math.round(p || 0)} size="small" style={{ width: 120 }} />,
    },
    {
      title: 'Quizzes Completed', dataIndex: 'quizzes_completed', key: 'quizzes',
    },
    {
      title: 'Assignments Submitted', dataIndex: 'submissions_count', key: 'submissions',
    },
    {
      title: 'Last Active', dataIndex: 'last_active', key: 'last_active',
      render: (d: string) => d ? new Date(d).toLocaleDateString() : '-',
    },
  ];

  if (loading) return <div style={{ textAlign: 'center', padding: 80 }}><Spin size="large" /></div>;

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <Title level={4} style={{ margin: 0 }}>Analytics</Title>
        <Space>
          <Select
            placeholder="Select a course"
            allowClear
            style={{ width: 250 }}
            value={selectedCourse}
            onChange={(val) => setSelectedCourse(val)}
            options={courses.map((c: any) => ({ label: c.title || c.name, value: c.uuid || c.id }))}
          />
        </Space>
      </div>

      {stats && (
        <Row gutter={[16, 16]} style={{ marginBottom: 24 }}>
          <Col xs={24} sm={selectedCourse ? 8 : 6}>
            <Card><Statistic title="Total Students" value={stats.total_students ?? stats.student_count ?? 0} prefix={<UserOutlined />} /></Card>
          </Col>
          <Col xs={24} sm={selectedCourse ? 8 : 6}>
            <Card><Statistic title="Completion Rate" value={stats.completion_rate ?? 0} suffix="%" prefix={<CheckCircleOutlined />} precision={1} /></Card>
          </Col>
          {!selectedCourse && (
            <>
              <Col xs={24} sm={6}>
                <Card><Statistic title="Active Courses" value={stats.active_courses ?? 0} prefix={<RiseOutlined />} /></Card>
              </Col>
              <Col xs={24} sm={6}>
                <Card><Statistic title="Avg Engagement" value={stats.average_engagement ?? 0} suffix="%" prefix={<ClockCircleOutlined />} precision={1} /></Card>
              </Col>
            </>
          )}
          {selectedCourse && (
            <Col xs={24} sm={8}>
              <Card><Statistic title="Avg Score" value={stats.average_score ?? 0} suffix="%" precision={1} /></Card>
            </Col>
          )}
        </Row>
      )}

      {stats?.recent_events?.length > 0 && (
        <Card title="Recent Events" style={{ marginBottom: 24 }}>
          <Table
            dataSource={stats.recent_events}
            columns={[
              { title: 'Event', dataIndex: 'event', key: 'event', render: (e: string) => <Tag>{e}</Tag> },
              { title: 'User', dataIndex: 'user_name', key: 'user' },
              { title: 'Date', dataIndex: 'occurred_at', key: 'date', render: (d: string) => new Date(d).toLocaleString() },
            ]}
            rowKey="id"
            pagination={{ pageSize: 10 }}
            size="small"
          />
        </Card>
      )}

      {selectedCourse && engagement.length > 0 && (
        <Card title="Student Engagement">
          <Table
            dataSource={engagement}
            columns={engColumns}
            rowKey="id"
            pagination={{ pageSize: 20 }}
            size="middle"
          />
        </Card>
      )}

      {!stats && !selectedCourse && (
        <Card><Empty description="No analytics data available" /></Card>
      )}
    </div>
  );
}
