import { Routes, Route, Navigate } from 'react-router-dom';
import ProtectedRoute from './components/ProtectedRoute';
import MainLayout from './layouts/MainLayout';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import Courses from './pages/Courses';
import CourseDetail from './pages/CourseDetail';
import Quiz from './pages/Quiz';
import Messages from './pages/Messages';
import Academics from './pages/Academics';
import Registrations from './pages/Registrations';
import Classes from './pages/Classes';
import ClassDetail from './pages/ClassDetail';
import Gradebook from './pages/Gradebook';
import Analytics from './pages/Analytics';
import Profile from './pages/Profile';
import LessonView from './pages/LessonView';
import MyCourses from './pages/MyCourses';
import Settings from './pages/Settings';

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        element={
          <ProtectedRoute>
            <MainLayout />
          </ProtectedRoute>
        }
      >
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/courses" element={<Courses />} />
        <Route path="/my-courses" element={<MyCourses />} />
        <Route path="/courses/:courseId" element={<CourseDetail />} />
        <Route path="/courses/:courseId/lessons/:lessonId" element={<LessonView />} />
        <Route path="/quiz/:id" element={<Quiz />} />
        <Route path="/messages" element={<Messages />} />
        <Route path="/academics" element={<Academics />} />
        <Route path="/registrations" element={<Registrations />} />
        <Route path="/classes" element={<Classes />} />
        <Route path="/classes/:id" element={<ClassDetail />} />
        <Route path="/gradebook" element={<Gradebook />} />
        <Route path="/gradebook/course/:courseId" element={<Gradebook />} />
        <Route path="/analytics" element={<Analytics />} />
        <Route path="/profile" element={<Profile />} />
        <Route path="/settings" element={<Settings />} />
      </Route>
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  );
}
