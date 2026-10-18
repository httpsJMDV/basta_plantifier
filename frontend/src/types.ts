export interface User {avatar_url:string|null; id:number; name:string; email:string; username:string|null; bio:string|null; municipality:string|null; interests:string[]; purpose:string|null; experience:string|null; discovery_enabled:boolean; friend_request_privacy:string; verified:boolean; is_admin:boolean; }
export interface Shared { [key:string]:unknown; auth:{user:User|null}; recentChats:{id:number;title:string;updated_at:string}[]; flash:{success?:string;error?:string}; announcement?:string; }
export interface Page<T> {data:T[];current_page:number;last_page:number;total:number;prev_page_url:string|null;next_page_url:string|null;}
export interface Media { id:number;url:string;width:number;height:number; }
export interface Identification {source?:string;score?:number|null;candidates?:{scientific_name:string;common_name:string|null;score:number}[];model_version?:string|null;explanation_model?:string;explanation_provider?:string;status:'suggested'|'ambiguous'|'unknown';common_name:string|null;scientific_name:string|null;family:string|null;traits:string;habitat:string;care:string;safety:string;uncertainty:string;alternatives:string[];}
export interface Message {attachments?:Media[];response_style?:string;id:number;role:'user'|'assistant';body:string;status:string;media:Media|null;identification:Identification|null;created_at:string;}
export interface Conversation {id:number;title:string;messages:Message[];}
export interface Plant {id:number;common_name:string;scientific_name:string|null;family:string|null;description:string|null;care:string|null;safety:string|null;relationship:string;favorite:boolean;archived_at:string|null;media:Media|null;created_at:string;provenance:{source:string;model:string;identified_at:string;conversation_id:number;identification:Identification}|null;observations:Observation[];notes:Note[];}
export interface Note {id:number;title:string;body:string;plant_record_id:number|null;plant?:{id:number;common_name:string}|null;updated_at:string;}
export interface Observation {id:number;body:string;observed_on:string;height_cm:string|null;media:Media|null;}
export interface PlantOption {id:number;common_name:string;}
export interface Share {share_id:number;common_name:string;scientific_name:string|null;description:string|null;care:string|null;safety:string|null;media_url:string|null;source:string;}
export interface Comment {avatar_url:string|null;id:number;post_id:number;user_id:number;name:string;body:string;species:string|null;accepted:boolean;votes:number;voted:boolean;}
export interface Post {avatar_url:string|null;id:number;user_id:number;name:string;username:string|null;body:string;kind:string;media_id:number|null;area:string|null;closed_at:string|null;created_at:string;comments:Comment[];shares:Share[];}
export interface Story {id:number;user_id:number;name:string;caption:string|null;media_id:number;audience:string;expires_at:string;}
export interface Person {avatar_url:string|null;id:number;name:string;username:string|null;bio:string|null;municipality:string|null;interests:string[];mutual_friends:number;reasons:string[];}
export interface Relationship {id:number;user_id:number;target_id:number;kind:string;status:string;}
export interface Pin {id:number;title:string;area:string|null;description:string|null;observed_on:string;visibility:string;sensitive:boolean;plant_record_id:number|null;common_name:string;latitude:number;longitude:number;approximate:boolean;}
