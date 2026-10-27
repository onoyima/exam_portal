import { useState, useEffect } from 'react';
import { Card, List, Spin, Typography, Breadcrumb, Empty, Button } from 'antd';
import { HomeOutlined, RightOutlined } from '@ant-design/icons';
import { api } from '../api/client';

const { Title } = Typography;

interface Faculty { id: number; name: string; abb: string; }
interface Department { id: number; name: string; abb: string; faculty_id: number; }
interface CourseStudy { id: number; name: string; program_name?: string; }
interface AcadCourse { id: number; name?: string; code?: string; title?: string; course_code?: string; course_title?: string; }

export default function Academics() {
  const [faculties, setFaculties] = useState<Faculty[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [courseStudies, setCourseStudies] = useState<CourseStudy[]>([]);
  const [courses, setCourses] = useState<AcadCourse[]>([]);
  const [breadcrumb, setBreadcrumb] = useState<{ label: string; onClick?: () => void }[]>([
    { label: 'Faculties' },
  ]);
  const [loading, setLoading] = useState(true);
  const [facultyId, setFacultyId] = useState<number | null>(null);
  const [deptId, setDeptId] = useState<number | null>(null);
  const [csId, setCsId] = useState<number | null>(null);

  useEffect(() => {
    api.get('/academic/faculties').then((res: any) => {
      setFaculties(res.faculties || []);
    }).catch(() => {}).finally(() => setLoading(false));
  }, []);

  const showDepartments = async (fid: number, name: string) => {
    setFacultyId(fid);
    setCourseStudies([]);
    setCourses([]);
    setLoading(true);
    setBreadcrumb([
      { label: 'Faculties', onClick: () => resetToFaculties() },
      { label: name },
    ]);
    try {
      const res = await api.get(`/academic/departments?faculty_id=${fid}`) as any;
      setDepartments(res.departments || []);
    } catch { setDepartments([]); }
    setLoading(false);
  };

  const showCourseStudies = async (did: number, name: string) => {
    setDeptId(did);
    setCourses([]);
    setLoading(true);
    setBreadcrumb([
      { label: 'Faculties', onClick: () => resetToFaculties() },
      { label: 'Depts', onClick: () => showDepartments(facultyId!, '') },
      { label: name },
    ]);
    try {
      const res = await api.get(`/academic/course-studies?department_id=${did}`) as any;
      setCourseStudies(res.course_studies || []);
    } catch { setCourseStudies([]); }
    setLoading(false);
  };

  const showCourses = async (cid: number, name: string) => {
    setCsId(cid);
    setLoading(true);
    setBreadcrumb([
      { label: 'Faculties', onClick: () => resetToFaculties() },
      { label: 'Depts', onClick: () => showDepartments(facultyId!, '') },
      { label: 'Programs', onClick: () => showCourseStudies(deptId!, '') },
      { label: name },
    ]);
    try {
      const res = await api.get(`/academic/courses?course_study_id=${cid}`) as any;
      setCourses(res.courses || []);
    } catch { setCourses([]); }
    setLoading(false);
  };

  const resetToFaculties = () => {
    setFacultyId(null);
    setDeptId(null);
    setCsId(null);
    setDepartments([]);
    setCourseStudies([]);
    setCourses([]);
    setBreadcrumb([{ label: 'Faculties' }]);
  };

  const renderContent = () => {
    if (courses.length > 0) {
      return (
        <List
          dataSource={courses}
          renderItem={(c) => (
            <List.Item>
              <List.Item.Meta
                title={`${c.course_code || c.code || ''} — ${c.course_title || c.title || c.name || 'Unnamed'}`}
              />
            </List.Item>
          )}
        />
      );
    }
    if (courseStudies.length > 0) {
      return (
        <List
          dataSource={courseStudies}
          renderItem={(cs) => (
            <List.Item
              actions={[<Button type="link" onClick={() => showCourses(cs.id, cs.name)}>View Courses <RightOutlined /></Button>]}
            >
              <List.Item.Meta title={cs.name} description={cs.program_name} />
            </List.Item>
          )}
        />
      );
    }
    if (departments.length > 0) {
      return (
        <List
          dataSource={departments}
          renderItem={(d) => (
            <List.Item
              actions={[<Button type="link" onClick={() => showCourseStudies(d.id, d.name)}>View Programs <RightOutlined /></Button>]}
            >
              <List.Item.Meta title={`${d.abb || ''} — ${d.name}`} />
            </List.Item>
          )}
        />
      );
    }
    return (
      <List
        dataSource={faculties}
        renderItem={(f) => (
          <List.Item
            actions={[<Button type="link" onClick={() => showDepartments(f.id, f.name)}>View Departments <RightOutlined /></Button>]}
          >
            <List.Item.Meta title={`${f.abb || ''} — ${f.name}`} />
          </List.Item>
        )}
      />
    );
  };

  return (
    <div>
      <Title level={4} style={{ marginBottom: 16 }}>Academics</Title>
      <Breadcrumb
        items={breadcrumb.map((b) => ({
          title: b.onClick ? <a onClick={b.onClick}>{b.label}</a> : b.label,
        }))}
        style={{ marginBottom: 16 }}
      />
      <Card>
        <Spin spinning={loading}>{renderContent()}</Spin>
      </Card>
    </div>
  );
}
