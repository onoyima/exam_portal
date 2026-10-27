import { useState, useEffect, useCallback, useRef } from 'react';
import { Row, Col, Card, Input, Spin, Tag, Typography, Empty, Alert, Button, Pagination, Space, Tooltip } from 'antd';
import { SearchOutlined, ReloadOutlined, BookOutlined, BarChartOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';
import { api } from '../api/client';

const { Title, Text, Paragraph } = Typography;

interface Course {
  id: number;
  uuid: string;
  title: string;
  code: string;
  level: string;
  credits: number;
  status: string;
  short_description: string;
  thumbnail_url: string;
  category_name?: string;
  lesson_count?: number;
  module_count?: number;
  created_at: string;
}

const levelColors: Record<string, string> = {
  '100': '#0891b2',
  '200': '#6366f1',
  '300': '#f59e0b',
  '400': '#16a34a',
  '500': '#dc2626',
};

export default function Courses() {
  const navigate = useNavigate();
  const [courses, setCourses] = useState<Course[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [totalPages, setTotalPages] = useState(1);
  const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const fetchCourses = useCallback(async (searchTerm: string, pageNum: number) => {
    setLoading(true);
    setError(null);
    try {
      const resp = await api.get<any>(`/courses?page=${pageNum}&per_page=24${searchTerm ? `&search=${encodeURIComponent(searchTerm)}` : ''}`);
      console.log('[Courses] /courses response keys:', Object.keys(resp));
      console.log('[Courses] first course sample:', resp.courses?.[0] ? JSON.stringify(resp.courses[0], null, 2) : 'no courses');
      console.log('[Courses] meta:', JSON.stringify(resp.meta, null, 2));
      setCourses(resp.courses || []);
      setTotal(resp.meta?.total || 0);
      setTotalPages(resp.meta?.total_pages || 1);
    } catch (err: any) {
      console.error('[Courses] fetch error:', err?.response?.status, err?.response?.data, err?.message, err);
      setError(err?.message || 'Failed to load courses');
      setCourses([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchCourses(search, page);
  }, [page, fetchCourses]);

  const handleSearchChange = (val: string) => {
    setSearch(val);
    if (searchTimer.current) clearTimeout(searchTimer.current);
    searchTimer.current = setTimeout(() => {
      setPage(1);
      fetchCourses(val, 1);
    }, 400);
  };

  const handleRetry = () => {
    fetchCourses(search, page);
  };

  return (
    <div>
      <div style={{
        background: 'linear-gradient(135deg, #0891b2 0%, #06b6d4 100%)',
        margin: '-24px -24px 24px',
        padding: '28px 24px',
        borderRadius: '0 0 24px 24px',
      }}>
        <Row align="middle" justify="space-between" wrap gutter={[16, 12]}>
          <Col>
            <Title level={4} style={{ margin: 0, color: '#fff' }}>Courses</Title>
            <Text style={{ color: 'rgba(255,255,255,0.8)', fontSize: 13 }}>
              {total > 0 ? `${total} course${total !== 1 ? 's' : ''} available` : 'Browse available courses'}
            </Text>
          </Col>
          <Col>
            <Input
              prefix={<SearchOutlined style={{ color: 'rgba(255,255,255,0.6)' }} />}
              placeholder="Search by title or code..."
              value={search}
              onChange={(e) => handleSearchChange(e.target.value)}
              style={{ width: 280, background: 'rgba(255,255,255,0.15)', border: '1px solid rgba(255,255,255,0.25)', color: '#fff' }}
              allowClear
              onClear={() => { setSearch(''); setPage(1); fetchCourses('', 1); }}
            />
          </Col>
        </Row>
      </div>

      {error && (
        <Alert
          message="Failed to load courses"
          description={error}
          type="error"
          showIcon
          style={{ marginBottom: 16, borderRadius: 8 }}
          action={<Button size="small" danger onClick={handleRetry} icon={<ReloadOutlined />}>Retry</Button>}
        />
      )}

      {loading ? (
        <Row gutter={[16, 16]}>
          {Array.from({ length: 8 }).map((_, i) => (
            <Col xs={24} sm={12} lg={8} xl={6} key={i}>
              <Card style={{ borderRadius: 12, overflow: 'hidden' }}>
                <div style={{ height: 4, background: '#e2e8f0', marginBottom: 16, borderRadius: 2 }} />
                <div style={{ height: 20, width: '70%', background: '#e2e8f0', borderRadius: 4, marginBottom: 8 }} />
                <div style={{ height: 14, width: '40%', background: '#e2e8f0', borderRadius: 4, marginBottom: 12 }} />
                <div style={{ display: 'flex', gap: 8, marginBottom: 8 }}>
                  <div style={{ height: 22, width: 60, background: '#e2e8f0', borderRadius: 11 }} />
                  <div style={{ height: 22, width: 70, background: '#e2e8f0', borderRadius: 11 }} />
                </div>
                <div style={{ height: 32, width: '100%', background: '#e2e8f0', borderRadius: 4 }} />
              </Card>
            </Col>
          ))}
        </Row>
      ) : courses.length === 0 ? (
        <Card style={{ borderRadius: 12 }}>
          <Empty
            description={search ? `No courses matching "${search}"` : 'No courses available yet'}
            image={Empty.PRESENTED_IMAGE_SIMPLE}
          >
            {search ? (
              <Button onClick={() => { setSearch(''); setPage(1); fetchCourses('', 1); }}>Clear Search</Button>
            ) : null}
          </Empty>
        </Card>
      ) : (
        <>
          <div style={{ marginBottom: 12, color: '#64748b', fontSize: 13 }}>
            Showing <Text strong>{courses.length}</Text> of <Text strong>{total}</Text> course{courses.length !== 1 ? 's' : ''}
          </div>
          <Row gutter={[16, 16]}>
            {courses.map((c) => {
              const accentColor = levelColors[c.level] || '#0891b2';
              return (
                <Col xs={24} sm={12} lg={8} xl={6} key={c.id}>
                  <Card
                    hoverable
                    onClick={() => c.uuid ? navigate(`/courses/${c.uuid}`) : null}
                    style={{
                      borderRadius: 12, height: '100%', display: 'flex', flexDirection: 'column',
                      overflow: 'hidden', transition: 'all 0.25s ease', border: '1px solid #f1f5f9',
                    }}
                    styles={{ body: { flex: 1, display: 'flex', flexDirection: 'column', padding: 0 } }}
                  >
                    <div style={{ height: 4, background: `linear-gradient(90deg, ${accentColor}, ${accentColor}66)` }} />
                    <div style={{ padding: '16px 18px', flex: 1, display: 'flex', flexDirection: 'column' }}>
                      <div style={{ marginBottom: 10 }}>
                        <BookOutlined style={{ fontSize: 24, color: accentColor }} />
                      </div>
                      <Tooltip title={c.title}>
                        <Title level={5} style={{ margin: 0, fontSize: 14, color: '#0f172a', lineHeight: 1.4 }} ellipsis={{ rows: 2 }}>
                          {c.title}
                        </Title>
                      </Tooltip>
                      <div style={{ marginTop: 6, display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                        {c.code && (
                          <Tag style={{ borderRadius: 6, fontSize: 11, margin: 0, background: '#f0f9ff', border: 'none', color: '#0369a1' }}>
                            {c.code}
                          </Tag>
                        )}
                        {c.level && (
                          <Tag style={{ borderRadius: 6, fontSize: 11, margin: 0, background: `${accentColor}15`, border: 'none', color: accentColor }}>
                            Level {c.level}
                          </Tag>
                        )}
                        {c.category_name && (
                          <Tag style={{ borderRadius: 6, fontSize: 11, margin: 0, background: '#f0fdf4', border: 'none', color: '#15803d' }}>
                            {c.category_name}
                          </Tag>
                        )}
                      </div>
                      {c.short_description && (
                        <Paragraph
                          style={{ marginTop: 8, fontSize: 12, color: '#64748b', lineHeight: 1.5, flex: 1 }}
                          ellipsis={{ rows: 2 }}
                        >
                          {c.short_description}
                        </Paragraph>
                      )}
                      <div style={{
                        marginTop: 'auto', paddingTop: 12, borderTop: '1px solid #f1f5f9',
                        display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                      }}>
                        <Text style={{ fontSize: 12, color: '#64748b' }}>
                          <BarChartOutlined style={{ marginRight: 4 }} />{c.credits ?? 3} Credits
                        </Text>
                        <Tag color={c.status === 'published' ? 'green' : c.status === 'draft' ? 'orange' : 'green'}
                          style={{ borderRadius: 6, fontSize: 10, flexShrink: 0 }}>
                          {c.status === 'published' ? 'Published' : c.status === 'draft' ? 'Draft' : c.status ? c.status : 'Published'}
                        </Tag>
                      </div>
                    </div>
                  </Card>
                </Col>
              );
            })}
          </Row>
          {totalPages > 1 && (
            <div style={{ marginTop: 24, textAlign: 'right' }}>
              <Pagination
                current={page}
                total={total}
                pageSize={24}
                onChange={(p) => { setPage(p); window.scrollTo({ top: 0, behavior: 'smooth' }); }}
                showSizeChanger={false}
                showTotal={(t) => `${t} courses total`}
              />
            </div>
          )}
        </>
      )}
    </div>
  );
}
