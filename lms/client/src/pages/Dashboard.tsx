import { useState, useEffect } from 'react';
import { Row, Col, Card, Statistic, Typography, List, Tag, Spin, Progress, Empty, Button } from 'antd';
import { BookOutlined, CheckCircleOutlined, BellOutlined, ArrowRightOutlined, UserOutlined, TeamOutlined, ReadOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../contexts/AuthContext';

const { Title, Text } = Typography;

export default function Dashboard() {
  const navigate = useNavigate();
  const { user } = useAuth();
  const role = user?.role || user?.portal_user_type || '';
  const isStudent = role === 'student';
  const isStaff = role === 'examiner' || role === 'instructor' || role === 'ta';
  const isAdmin = role === 'super_admin' || role === 'admin';

  const [dashboardData, setDashboardData] = useState<any>(null);
  const [courses, setCourses] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const endpoint = isStaff ? '/dashboard/instructor' : isAdmin ? '/dashboard/admin' : '/dashboard/student';
    Promise.all([
      api.get(endpoint).catch(() => ({})),
      api.get('/courses/my').catch(() => ({ courses: [] })),
    ]).then(([d, c]) => {
      setDashboardData(d as any);
      setCourses((c as any)?.courses || []);
    }).finally(() => setLoading(false));
  }, [isStaff, isAdmin]);

  if (loading) return <div style={{ textAlign: 'center', padding: 80 }}><Spin size="large" /></div>;

  const renderStudentStats = () => (
    <Row gutter={[16, 16]}>
      <Col xs={24} sm={8}>
        <Card hoverable onClick={() => navigate('/my-courses')}>
          <Statistic title="Active Enrolled" value={dashboardData?.active_enrollments ?? 0} prefix={<BookOutlined />} valueStyle={{ color: '#4f46e5' }} />
        </Card>
      </Col>
      <Col xs={24} sm={8}>
        <Card hoverable onClick={() => navigate('/gradebook')}>
          <Statistic title="Completed" value={dashboardData?.completed_courses ?? 0} prefix={<CheckCircleOutlined />} valueStyle={{ color: '#16a34a' }} />
        </Card>
      </Col>
      <Col xs={24} sm={8}>
        <Card>
          <Statistic title="Notifications" value={dashboardData?.unread_notifications ?? 0} prefix={<BellOutlined />} valueStyle={{ color: '#ea580c' }} />
        </Card>
      </Col>
    </Row>
  );

  const renderStaffStats = () => (
    <Row gutter={[16, 16]}>
      <Col xs={24} sm={8}>
        <Card hoverable onClick={() => navigate('/my-courses')}>
          <Statistic title="My Courses" value={dashboardData?.stats?.total_courses ?? courses.length} prefix={<ReadOutlined />} valueStyle={{ color: '#0891b2' }} />
        </Card>
      </Col>
      <Col xs={24} sm={8}>
        <Card>
          <Statistic title="Total Enrollments" value={dashboardData?.stats?.total_enrollments ?? 0} prefix={<TeamOutlined />} valueStyle={{ color: '#4f46e5' }} />
        </Card>
      </Col>
      <Col xs={24} sm={8}>
        <Card>
          <Statistic title="Active Enrollments" value={dashboardData?.stats?.active_enrollments ?? 0} prefix={<UserOutlined />} valueStyle={{ color: '#16a34a' }} />
        </Card>
      </Col>
    </Row>
  );

  const renderAdminStats = () => (
    <Row gutter={[16, 16]}>
      <Col xs={24} sm={8}>
        <Card>
          <Statistic title="Total Users" value={dashboardData?.total_users ?? 0} prefix={<UserOutlined />} />
        </Card>
      </Col>
      <Col xs={24} sm={8}>
        <Card hoverable onClick={() => navigate('/courses')}>
          <Statistic title="Total Courses" value={dashboardData?.total_courses ?? 0} prefix={<BookOutlined />} />
        </Card>
      </Col>
      <Col xs={24} sm={8}>
        <Card hoverable onClick={() => navigate('/classes')}>
          <Statistic title="Active Enrollments" value={dashboardData?.active_enrollments ?? 0} prefix={<TeamOutlined />} valueStyle={{ color: '#16a34a' }} />
        </Card>
      </Col>
    </Row>
  );

  return (
    <div>
      <Title level={4} style={{ marginBottom: 24 }}>Dashboard</Title>

      {isStudent && renderStudentStats()}
      {isStaff && renderStaffStats()}
      {isAdmin && renderAdminStats()}

      <div style={{ marginTop: 32 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
          <Title level={5} style={{ margin: 0 }}>
            {isStudent ? 'My Enrolled Courses' : isStaff ? 'My Courses' : 'Courses'}
          </Title>
          <Button type="link" onClick={() => navigate('/my-courses')}>
            View all <ArrowRightOutlined />
          </Button>
        </div>
        {courses.length === 0 ? (
          <Card>
            <Empty
              description={isStudent ? 'You are not enrolled in any courses yet' : isStaff ? 'No courses assigned yet' : 'No courses available'}
              image={Empty.PRESENTED_IMAGE_SIMPLE}
            >
              {isStudent && <Button type="primary" onClick={() => navigate('/courses')}>Browse Courses</Button>}
            </Empty>
          </Card>
        ) : (
          <Row gutter={[16, 16]}>
            {courses.slice(0, 4).map((c: any) => (
              <Col xs={24} sm={12} lg={6} key={c.id}>
                <Card
                  hoverable
                  onClick={() => navigate(`/courses/${c.uuid}`)}
                  style={{ borderRadius: 8, height: '100%' }}
                >
                  <Card.Meta
                    title={<span style={{ fontSize: 14 }}>{c.title}</span>}
                    description={
                      <div>
                        <Tag>{c.code || 'N/A'}</Tag>
                        {isStudent && typeof c.progress_percent === 'number' && (
                          <div style={{ marginTop: 12 }}>
                            <Progress percent={Math.round(c.progress_percent)} size="small" />
                            <div style={{ fontSize: 12, color: '#888', marginTop: 4 }}>
                              {Math.round(c.progress_percent)}% complete
                            </div>
                          </div>
                        )}
                      </div>
                    }
                  />
                </Card>
              </Col>
            ))}
          </Row>
        )}
      </div>

      {dashboardData?.recent_activity?.length > 0 && (
        <div style={{ marginTop: 32 }}>
          <Title level={5} style={{ marginBottom: 16 }}>Recent Activity</Title>
          <Card>
            <List
              size="small"
              dataSource={dashboardData.recent_activity.slice(0, 5)}
              renderItem={(item: any) => (
                <List.Item>
                  <List.Item.Meta
                    title={item.action || item.type || 'Activity'}
                    description={new Date(item.created_at).toLocaleString()}
                  />
                </List.Item>
              )}
            />
          </Card>
        </div>
      )}

      {isStaff && dashboardData?.recent_courses?.length > 0 && (
        <div style={{ marginTop: 32 }}>
          <Title level={5} style={{ marginBottom: 16 }}>Recently Updated Courses</Title>
          <Card>
            <List
              size="small"
              dataSource={dashboardData.recent_courses.slice(0, 5)}
              renderItem={(item: any) => (
                <List.Item
                  onClick={() => navigate(`/courses/${item.uuid}`)}
                  style={{ cursor: 'pointer' }}
                >
                  <List.Item.Meta
                    title={item.title}
                    description={<Text type="secondary">{item.code} — Updated {new Date(item.updated_at).toLocaleDateString()}</Text>}
                  />
                </List.Item>
              )}
            />
          </Card>
        </div>
      )}
    </div>
  );
}
