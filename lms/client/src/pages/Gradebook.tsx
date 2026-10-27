import { useState, useEffect } from 'react';
import { Table, Card, Typography, Spin, Empty, Tag, Progress, Row, Col, Statistic, Select, Space } from 'antd';
import { BookOutlined, TrophyOutlined } from '@ant-design/icons';
import { useParams } from 'react-router-dom';
import { api } from '../api/client';

const { Title } = Typography;

export default function Gradebook() {
  const { courseId } = useParams();
  const [grades, setGrades] = useState<any[]>([]);
  const [summary, setSummary] = useState<any>(null);
  const [courses, setCourses] = useState<any[]>([]);
  const [selectedCourse, setSelectedCourse] = useState<string | undefined>(courseId);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/courses').then((res: any) => {
      setCourses(res?.courses || res || []);
    }).catch(() => {});
  }, []);

  useEffect(() => {
    setLoading(true);
    const endpoint = selectedCourse
      ? `/gradebook/course/${selectedCourse}`
      : '/gradebook/my';
    api.get(endpoint).then((res: any) => {
      if (selectedCourse) {
        setGrades(res?.grades || res || []);
        setSummary(null);
      } else {
        setSummary(res?.summary || null);
        setGrades(res?.grades || []);
      }
    }).catch(() => {
      setGrades([]);
      setSummary(null);
    }).finally(() => setLoading(false));
  }, [selectedCourse]);

  const columns = [
    { title: 'Course', dataIndex: 'course_name', key: 'course', ellipsis: true },
    { title: 'Item', dataIndex: 'item_name', key: 'item', ellipsis: true },
    {
      title: 'Score', dataIndex: 'points_earned', key: 'score',
      render: (_: any, r: any) => `${r.points_earned ?? 0} / ${r.points_possible ?? 0}`,
    },
    {
      title: 'Percentage', key: 'pct',
      render: (_: any, r: any) => {
        const pct = r.points_possible ? Math.round((r.points_earned / r.points_possible) * 100) : 0;
        return <Progress percent={pct} size="small" style={{ width: 120 }} />;
      },
    },
    {
      title: 'Grade', dataIndex: 'grade', key: 'grade',
      render: (g: string) => g ? <Tag color={g >= 'A' ? 'green' : g >= 'B' ? 'blue' : g >= 'C' ? 'orange' : 'red'}>{g}</Tag> : '-',
    },
    { title: 'Date', dataIndex: 'created_at', key: 'date', render: (d: string) => d ? new Date(d).toLocaleDateString() : '-' },
  ];

  if (loading) return <div style={{ textAlign: 'center', padding: 80 }}><Spin size="large" /></div>;

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 }}>
        <Title level={4} style={{ margin: 0 }}>Gradebook</Title>
        <Space>
          <Select
            placeholder="Filter by course"
            allowClear
            style={{ width: 250 }}
            value={selectedCourse}
            onChange={(val) => setSelectedCourse(val)}
            options={courses.map((c: any) => ({ label: c.title || c.name, value: c.uuid || c.id }))}
          />
        </Space>
      </div>

      {!selectedCourse && summary && (
        <Row gutter={[16, 16]} style={{ marginBottom: 24 }}>
          <Col xs={24} sm={8}>
            <Card><Statistic title="Overall GPA" value={summary.gpa ?? '-'} prefix={<TrophyOutlined />} precision={2} /></Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card><Statistic title="Courses Enrolled" value={summary.course_count ?? 0} prefix={<BookOutlined />} /></Card>
          </Col>
          <Col xs={24} sm={8}>
            <Card>
              <Statistic title="Average Score" value={summary.average_score ?? 0} suffix="%" precision={1} />
            </Card>
          </Col>
        </Row>
      )}

      <Card>
        {grades.length === 0 ? (
          <Empty description="No grade entries found" />
        ) : (
          <Table
            dataSource={grades}
            columns={columns}
            rowKey="id"
            pagination={{ pageSize: 20, showSizeChanger: true }}
            size="middle"
          />
        )}
      </Card>
    </div>
  );
}
