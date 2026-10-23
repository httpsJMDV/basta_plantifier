import AdminLayout from '../components/AdminLayout';
import {AdminAccounts,AdminModeration} from '../components/AdminReviews';
import {AdminDashboard,AdminMaps,AdminAI,AdminSecurityCenter,AdminSettings} from '../components/AdminScreens';
import {AdminProviders} from '../components/AdminProviders';
import type {AdminProps} from '../adminTypes';
import {aiIssues} from '../adminTypes';
export default function Admin(props:AdminProps){
 const sections={dashboard:AdminDashboard,users:AdminAccounts,reports:AdminModeration,maps:AdminMaps,ai:AdminAI,providers:AdminProviders,audit:AdminSecurityCenter,settings:AdminSettings};
 const Section=sections[props.section as keyof typeof sections]??AdminDashboard;
 return <AdminLayout section={props.section} stepUp={props.stepUp} aiAlert={aiIssues(props.aiHealth).length>0}><Section {...props}/></AdminLayout>;
}
